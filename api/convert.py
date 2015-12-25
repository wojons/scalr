#!/usr/bin/env python
# This reads the filename to process from stdin.
# It is best used with fswatch:
# fswatch --latency=0.01 --monitor=fsevents_monitor swagger.yaml | python convert.py
import tempfile
import traceback
import subprocess
import copy
import sys
import json

import yaml
import boto
from boto.s3.key import Key


UPLOAD_BUCKET = "scalr-api-mockup"
UPLOAD_NAME = "mockup.json"


def deref(obj, global_data, inject_in_ref=None):
    """
    Dereference one pointer.
    """
    ref = obj.get("$ref")
    if ref is None:  # Not a reference, ignore
        return obj
    _, ref_type, ref_name = ref.split("/")
    obj = global_data[ref_type][ref_name]

    if 'allOf' in obj:
        process_composition(ref_name, obj, global_data)

    if inject_in_ref is not None:
        for k, v in inject_in_ref.items():
            append_or_initialize_list(obj, k, v)

    return copy.deepcopy(obj)


def deref_recurse(container, global_data, inject_in_ref=None):
    """
    Recursively dereference pointers found in a container.
    """
    if container is None:
        return

    if isinstance(container, list):
        in_place_replace_list(container, [deref(v, global_data, inject_in_ref) for v in container])
    elif isinstance(container, dict):
        in_place_replace_dict(container, dict([(k, deref(v, global_data, inject_in_ref)) for k, v in container.items()]))
    else:
        raise RuntimeError("Unable to deref: %s" % repr(container))


def find_references(obj, all_references=None):
    if all_references is None:
        all_references = set()

    if isinstance(obj, dict):
        # Check for a reference here!
        ref = obj.get("$ref")
        all_references.add(ref)
        find_references(obj.values(), all_references)  # Send the values to the list branch
    elif isinstance(obj, list):
        map(lambda sub_obj: find_references(sub_obj, all_references), obj)
    else:
        pass

    return all_references


def str_lower_first(s):
    return "".join([s[0].lower(), s[1:]])


def append_or_initialize_list(d, key, value):
    # TODO - Rename to indicate uniqueness
    if key not in d:
        d[key] = []
    l = d[key]
    if value in l:
        return
    l.append(value)


def in_place_replace_dict(old_dict, new_dict):
    # old_dict.clear()
    old_dict.update(new_dict)


def in_place_replace_list(old_list, new_list):
    old_list[:] = new_list


def process_composition(name, definition, global_data):
    # This method removes composition in place.
    # It bails out if the composition is in fact polymorphism, or if there is no composition going on.
    if "allOf" not in definition:
        return

    new_definition = {}
    for el in definition["allOf"]:

        ref = el.get("$ref")
        if ref is not None:
            # Check the referenced object for polymorphism. If that's the case, then inject *this* object
            # into "x-concreteTypes"
            inject = {}
            if "discriminator" in deref(el, global_data):
                inject.update({"x-concreteTypes": {"$ref": "#/definitions/{0}".format(name)}})
            el = deref(el, global_data, inject_in_ref=inject)

        for k, v in el.items():
            if k == "discriminator":
                # TODO ... Remove this when polymorphism stops crashing the Swagger editor (but update autodoc first).

                # This is polymorphism, not composition. Bail out
                new_definition["x-abstractType"] = {"$ref": ref}
                new_definition["x-discriminator"] = v

                continue

            if k in ["x-concreteTypes", "x-abstractType"]:
                # If the parent is an abstract type, x-concreteTypes may be added. That doesn't mean we should copy
                # if there! Same goes for abstractType.
                continue

            if k == "description":
                new_definition[k] = v
                continue

            if isinstance(v, dict):
                new_val = new_definition.get(k, {})
                new_val.update(v)
                new_definition[k] = new_val
            elif isinstance(v, list):
                new_val = new_definition.get(k, [])
                new_val.extend(v)
                new_definition[k] = new_val
            else:
                raise Exception("Unexpected '{0}' ({1} {2}) in {3}".format(k, type(v), v, definition))

    in_place_replace_dict(definition, new_definition)

    del definition['allOf']


def pre_process(src_filename):
    with open(src_filename) as src_file:
        dat = yaml.load(src_file)

    # Start by walking the entire structure to find references
    # TODO - This is needed to avoid injecting un-needed references.

    all_references = find_references(dat)

    # Inject Envelopped Detail and List Definitions for each Object, as well as parameters #
    all_definitions = dat.get("definitions", {})
    all_parameters = dat.get("parameters", {})
    all_responses = dat.get("responses", {})

    base_response_properties = {
        "errors": {
            "type": "array",
            "readOnly": True,
            "items": {
                "$ref": "#/definitions/ApiMessage"
            }
        },
        "warnings": {
            "type": "array",
            "readOnly": True,
            "items": {
                "$ref": "#/definitions/ApiMessage"
            }
        },
        "meta": {
            "$ref": "#/definitions/ApiMetaContainer",
            "readOnly": True,
        }
    }

    all_responses["deleteSuccessResponse"] = copy.deepcopy(base_response_properties)

    # Pre-process all definitions
    for name, definition in list(all_definitions.items()):  # list() -> Python 3
        if name.startswith("Api"):
            # "Api..." is for API objects only.
            continue

        process_composition(name, definition, dat)

        # Parameters
        if "id" in definition.get("properties", {}):
            id_param_name = "{0}Id".format(str_lower_first(name))
            all_parameters[id_param_name] = {
                "name": id_param_name,
                "in": "path",
                "type": definition["properties"]["id"]["type"],
                "required": True,
                "description": "The ID of a {0} object.".format(name),
                "x-references": {"$ref": "#/definitions/{0}".format(name)},
            }

        obj_param_name = "{0}Object".format(str_lower_first(name))
        all_parameters[obj_param_name] = {
            "name": obj_param_name,
            "description": "The JSON representation of a {0} object.".format(name),
            "in": "body",
            "schema": {"$ref": "#/definitions/{0}".format(name)},
            "required": True,
        }

        # Responses
        list_response_name = "{0}List".format(str_lower_first(name))
        list_response_definition = {
            "description": "A list of {0} objects.".format(name),
            "schema": {
                "$ref": "#/definitions/{0}ListResponse".format(name)
            }
        }

        detail_response_name = "{0}Detail".format(str_lower_first(name))
        detail_response_definition = {
            "description": "The JSON representation of a {0} object.".format(name),
            "schema": {
                "$ref": "#/definitions/{0}DetailResponse".format(name)
            }
        }

        for response_name, response_definition in [
                (detail_response_name, detail_response_definition),
                (list_response_name, list_response_definition),
        ]:
            if "#/responses/{0}".format(response_name) in all_references:
                all_responses[response_name] = response_definition
                all_references |= find_references(response_definition)


        # Responses

        list_response_name = "{0}ListResponse".format(name)
        list_response_definition = {
            "properties": {
                "data": {
                    "type": "array",
                    "readOnly": True,
                    "items": {
                        "$ref": "#/definitions/{0}".format(name),
                    }
                },
                "pagination": {
                    "$ref": "#/definitions/ApiPagination",
                    "readOnly": True,
                }
            },
            "x-derived": {"$ref": "#/definitions/{0}".format(name)},
        }
        list_response_definition["properties"].update(copy.deepcopy(base_response_properties))

        detail_response_name = "{0}DetailResponse".format(name)
        detail_response_definition = {
            "properties": {
                "data": {
                    "$ref": "#/definitions/{0}".format(name),
                },
            },
            "x-derived": {"$ref": "#/definitions/{0}".format(name)},
        }
        detail_response_definition["properties"].update(copy.deepcopy(base_response_properties))

        extra_definitions = [
            (detail_response_name, detail_response_definition),
            (list_response_name, list_response_definition),
        ]

        # Create a generic foreign key
        if "id" in definition.get("properties", {}):
            extra_definitions.append(("{0}ForeignKey".format(name), {
                "required": ["id"],
                "properties": {
                    "id": {
                        "type": definition["properties"]["id"]["type"],
                    }
                },
                "x-references": {"$ref": "#/definitions/{0}".format(name)},
                "x-derived": {"$ref": "#/definitions/{0}".format(name)},
            }))

        # Check what we should add. Declared but unused definitions are a warning
        # in Swagger and they crash the editor

        for extra_def_name, extra_def_definition in extra_definitions:
            if extra_def_name in all_definitions:
                # Already defined. Let the override be.
                continue
            if not "#/definitions/{0}".format(extra_def_name) in all_references:
                 # Not used. Don't include it
                continue
            all_definitions[extra_def_name] = extra_def_definition


    all_definitions["ApiPagination"] = {
        "properties": {
            "first": {
                "type": "string",
                "readOnly": True,
            },
            "prev": {
                "type": "string",
                "readOnly": True,
            },
            "next": {
                "type": "string",
                "readOnly": True,
            },
            "last": {
                "type": "string",
                "readOnly": True,
            },
        },
    }

    # Manually dereference responses and parameters #
    # The Swagger Editor only supports Definitions #
    global_data = {
        "parameters": dat.pop("parameters", {}),
        "responses": dat.pop("responses", {})
    }

    for path, path_definition in dat["paths"].items():
        if not path.endswith('/'):
            raise Exception('Path should end with "/": {0}'.format(path))
        # Dereference what Swagger's UI can't handle
        deref_recurse(path_definition.get("parameters"), global_data)

        for name, method in path_definition.items():
            if name == "parameters":
               continue

            # Inject 400 and 500
            responses = method.get("responses", {})
            for code, ref in [
                (400, '#/responses/clientError'),
                (401, '#/responses/authenticationError'),
                (403, '#/responses/permissionsError'),
                (404, '#/responses/notFoundError'),
                (409, '#/responses/conflictError'),
                (500, '#/responses/serverError'),
            ]:
                if code not in responses:
                    responses[code] = {"$ref": ref}

            # Dereference again
            deref_recurse(method.get("parameters"), global_data)
            deref_recurse(method.get("responses"), global_data)


    # Final pass. Create "x-usedIn".
    for path, path_definition in dat["paths"].items():
        for parameter in path_definition.get("parameters", []):
            deref(parameter.get("x-references", {}), dat, {"x-usedIn": path})

        for method, method_definition in path_definition.items():
            if method == "parameters":
                continue

            for parameter in method_definition.get("parameters", []):
                deref(parameter.get("schema", {}), dat, {"x-usedIn": path})

            for response_code, response_definition in method_definition.get("responses").items():
                deref(response_definition.get("schema", {}), dat, {"x-usedIn": path})

    return dat

def process_update(src_filename):
    dat = pre_process(src_filename)

    out_json = json.dumps(dat, indent=2)
    out_yaml = yaml.dump(dat, default_flow_style=False)

    print "Copying"
    proc = subprocess.Popen(["pbcopy"], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
    proc.communicate(out_yaml)
    ret = proc.wait()

    if ret:
        print "COPYING FAILED"

    try:
        validate_file = tempfile.NamedTemporaryFile(suffix=".json", delete=False)
        validate_file.write(out_json)
        validate_file.close()

        print "Validating", validate_file.name
        proc = subprocess.Popen(["swagger-tools", "validate", validate_file.name], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        _, err = proc.communicate()
        ret = proc.wait()
        if ret:
            print "VALIDATION FAILED"
            print err
        else:
            print "VALIDATION PASSED!"
    finally:
        #os.unlink(validate_file.name)
        pass




def main():
    if "upload" in sys.argv:
        print "Uploading!"
        if len(sys.argv) != 3:
            print "Args should be: [upload, file]"
            sys.exit(1)
        target = sys.argv[2]
        dat = pre_process(target)
        conn = boto.connect_s3()
        bucket = conn.get_bucket(UPLOAD_BUCKET)
        k = Key(bucket)
        k.key = UPLOAD_NAME
        k.set_contents_from_string(json.dumps(dat), {
            "Cache-Control": "no-cache, no-store, must-revalidate",
            "Pragma": "no-cache",
            "Expires": "0",
            "Content-Type": "application/json"
        })
        k.set_acl('public-read')
    elif "write" in sys.argv:
        if len(sys.argv) != 4:
            print "Args should be: [write, target, dest]"
            sys.exit(1)
        target, dest = sys.argv[2], sys.argv[3]
        dat = pre_process(target)
        with open(dest, 'w') as f:
            yaml.dump(dat, f, default_flow_style=False)
    else:
        print "Waiting for changes events"
        while 1:
            line = sys.stdin.readline()
            if not line:  # (EOF)
                print 'Done!'
                sys.exit(0)

            try:
                process_update(line.strip())
            except Exception:
                print "PROCESSING ERROR!"
                traceback.print_exc()


if __name__ == "__main__":
    main()

