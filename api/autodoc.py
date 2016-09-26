#!/usr/bin/env python
# coding:utf-8
import os
import sys
import logging
import shutil
import json
import errno
import copy

import yaml
import jinja2


logging.getLogger("autodoc")

base_path = {}

def _make_render_env(doc_root):
    tpl_dir = os.path.join(doc_root, "tpl")
    env = jinja2.Environment(loader=jinja2.FileSystemLoader(tpl_dir),
                             autoescape=False,
                             undefined=jinja2.StrictUndefined,
                             trim_blocks=True,
                             lstrip_blocks=True)

    for o in [link_to_ref, link_to_path, str_upper_first]:
        env.filters[o.__name__] = o

    for o in [json, prioritized_properties, property_attributes, parameters_by_type, is_filterable]:
        env.globals[o.__name__] = o

    return env


def prioritized_properties(obj):
    required = set(obj.get("required", []))
    # TODO

    def rank(prop_tuple):
        name, prop = prop_tuple
        return name in required, prop.get("x-createOnly") is not None, not prop.get("readOnly"), name

    return sorted(obj["properties"].items(), key=rank, reverse=True)


def is_filterable(obj, prop):
    name, _ = prop
    return name in obj.get("x-filterable", [])


def property_attributes(obj, prop):
    prop_name, prop_def = prop
    attrs = []

    if prop_name in obj.get("required", []):
        attrs.append("required")

    if prop_name in obj.get("x-createOnly", []):
        attrs.append("create-only")

    if "readOnly" in prop_def:
        attrs.append("read-only")

    if not attrs:
        attrs.append("optional")

    if is_filterable(obj, prop):
        attrs.append("filterable")

    return attrs


def str_upper_first(s):
    return "".join([s[0].upper(), s[1:]])


@jinja2.contextfilter
def link_to_ref(ctx, ref):
    name = ref["$ref"].rsplit("/", 1)[-1]
    depth = ctx.resolve("depth")
    return "[`{0}`](.{1}definitions/{0}.mkd)".format(name, "..".join(["/"] * (depth + 1)))


@jinja2.contextfilter
def link_to_path(ctx, path):
    depth = ctx.resolve("depth")
    return "[`{0}`](./{1}/rest{2}{0})".format(path, "/".join([".."] * depth), base_path[path])


def parameters_by_type(definition, method, param_type=None):
    all_params = []
    all_params.extend(definition.get("parameters", []))
    all_params.extend(definition.get(method, {}).get("parameters", []))
    return [param for param in all_params if param["in"] == param_type or param_type is None]


def _mkdir_p(path):
    try:
        os.makedirs(path)
    except OSError as exc:  # Python >2.5
        if exc.errno == errno.EEXIST and os.path.isdir(path):
            pass
        else:
            raise


def _merge(yaml1, yaml2):
    if isinstance(yaml1, dict) and isinstance(yaml2, dict):
        for k, v in yaml2.iteritems():
            if k not in yaml1:
                yaml1[k] = v
            else:
                yaml1[k] = _merge(yaml1[k], v)
    if isinstance(yaml1, list) and isinstance(yaml2, list):
        yaml1 += [o for o in yaml2 if o not in yaml1]
    return yaml1


def main(swaggers, doc_root):
    # Create all dirs
    cwd = os.path.dirname(os.path.abspath(__file__))
    doc_dir = os.path.join(doc_root, "autodoc")
    definitions_dir = os.path.join(doc_dir, "definitions")
    if os.path.exists(definitions_dir):
        shutil.rmtree(definitions_dir)
    _mkdir_p(definitions_dir)

    render_env = _make_render_env(doc_root)

    data = []
    for swagger in swaggers:
        with open(cwd + "/" + swagger) as f:
            data.append(yaml.load(f))
        api_dir = os.path.join(doc_dir, "rest{0}".format(data[-1]["basePath"]))
        if os.path.exists(api_dir):
            shutil.rmtree(api_dir)
        _mkdir_p(api_dir)
        for name, definition in data[-1]["definitions"].items():
            if "x-usedIn" not in definition:
                continue
            for path in definition["x-usedIn"]:
                base_path[path] = data[-1]["basePath"]

    definitions = data[0]["definitions"]
    if len(data) > 1:
        definitions = copy.deepcopy(definitions)
        for o in data[1:]:
            _merge(definitions, o["definitions"])

    # Document definitions
    definition_template = render_env.get_template("definition.mkd")
    for name, definition in definitions.items():
        for attr, value in definition.iteritems():
            if isinstance(value, list):
                value.sort()
                
        logging.info("Autogenerating documentation for: %s", name)
        with open(os.path.join(definitions_dir, "{0}.mkd".format(name)), "w") as f:
            ctx = {"name": name, "definition": definition, "depth": 1}
            doc = definition_template.render(ctx)
            f.write(doc.encode("utf-8"))

    for dat in data:
        render_env.globals["base_path"] = dat["basePath"]
        api_dir = os.path.join(doc_dir, "rest{0}".format(dat["basePath"]))
        method_template = render_env.get_template("method.mkd")
        for path, definition in dat["paths"].items():
            path_doc = os.path.join(api_dir, path.lstrip("/"))
            _mkdir_p(path_doc)

            for method in ["get", "post", "patch", "put", "delete"]:
                method_def = definition.get(method)  # TODO - Canonicalize case in convert.py
                if method_def is None:
                    continue
                logging.info("Autogenerating documentation for %s (%s)", path, method)
                with open(os.path.join(path_doc, "{0}.mkd".format(method.upper())), "w") as f:
                    ctx = {"path": path, "definition": definition, "method_definition": method_def, "method": method,
                           "depth": path.count("/") + dat["basePath"].count("/")}
                    doc = method_template.render(ctx)
                    f.write(doc.encode("utf-8"))


def pre_main():
    logging.basicConfig(level=logging.INFO)

    cwd = os.path.dirname(os.path.abspath(__file__))
    doc_root = os.path.join(cwd, "doc")

    import argparse
    parser = argparse.ArgumentParser("autodoc")
    parser.add_argument("--suffix", type=str,
                        help="swagger autogenerated file suffix, default '-autogenerated.yaml'")
    parser.add_argument("version", type=str, help='API version')
    ns = parser.parse_args()

    suffix = ns.suffix or "-autogenerated.yaml"

    swaggers = [f for f in os.listdir(os.path.join(cwd, ns.version))
                if os.path.isfile(os.path.join(cwd, ns.version, f)) and f.endswith(suffix)]

    if not swaggers:
        msg = "Autogenerated swagger files with suffix '{0}' was not found in directory {1}".format(
                suffix, os.path.join(cwd, ns.version))
        logging.error(msg)
        sys.exit(1)

    main(swaggers, doc_root)


if __name__ == "__main__":
    pre_main()
