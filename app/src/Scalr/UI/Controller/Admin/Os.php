<?php

use Scalr\Model\Entity\Os;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;


class Scalr_UI_Controller_Admin_Os extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/admin/os/view.js', ['os' => $this->getList()]);
    }

    public function xListAction()
    {
        $this->response->data([
            'os' => $this->getList()
        ]);
    }

    /**
     * @param string $id
     * @param string $status
     * @param string $name
     * @param string $family
     * @param string $generation
     * @param string $version
     * @throws Exception
     */
    public function xSaveAction($id, $status, $name, $family, $generation, $version = '')
    {
        $os = Os::findPk($id);
        if (!$os) {
            $os = new Os();
            $os->isSystem = 0;
            $os->id = $id;
        }

        $validator = new Validator();
        $validator->validate($id, 'id', Validator::NOEMPTY);
        $validator->validate($name, 'name', Validator::NOEMPTY);
        $validator->validate($family, 'family', Validator::NOEMPTY);
        $validator->validate($generation, 'generation', Validator::NOEMPTY);

        //check by name, family, generation,version
        $criteria = [];
        $criteria[] = ['name' => $name];
        $criteria[] = ['family' => $family];
        $criteria[] = ['generation' => $generation];
        $criteria[] = ['version' => $version];
        if ($os->id) {
            $criteria[] = ['id' => ['$ne' => $os->id]];
        }

        if (Os::findOne($criteria)) {
            $validator->addError('name', 'Operating system with such name, family, generation and version already exists');
        }

        if (!$validator->isValid($this->response)) return;

        $os->status = $status;
        if ($os->isSystem != 1) {
            $os->name = $name;
            $os->family = $family;
            $os->generation = $generation;
            $os->version = $version;
        }
        
        $os->save();

        $result = get_object_vars($os);
        $result['used'] = $os->getUsed();
        $this->response->data(['os' => $os]);
        $this->response->success('Operating system successfully saved');
    }

    private function getList()
    {
        $list = [];
        foreach (Os::find() as $entity) {
            $data = get_object_vars($entity);
            $data['used'] = $entity->getUsed();
            $list[] = $data;
        }

        return $list;
    }


    /**
     * @param JsonData $ids
     * @param string $action
     */
    public function xGroupActionHandlerAction(JsonData $ids, $action)
    {
        $processed = [];
        $errors = [];

        foreach($ids as $osId) {
            try {
                $os = Os::findPk($osId);

                switch($action) {
                    case 'delete':
                        if (!$os->isSystem) {
                            $os->delete();
                            $processed[] = $os->id;
                        } else {
                            throw new Scalr_Exception_Core('Operating system can\'t be removed');
                        }
                        break;

                    case 'activate':
                        $os->status = 'active';
                        $os->save();
                        $processed[] = $os->id;
                        break;

                    case 'deactivate':
                        $os->status = 'inactive';
                        $os->save();
                        $processed[] = $os->id;
                        break;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $num = count($ids);

        if (count($processed) == $num) {
            $this->response->success('All operating systems processed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully processed %d from %d operating systems. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(['processed' => $processed]);
    }

    /**
     * @param string $id
     * @throws Exception
     */
    public function xRemoveAction($id)
    {
        $os = Os::findPk($id);
        if (!$os) {
            throw new Scalr_Exception_Core('Operating system not found');
        }

        if ($os->isSystem == 1) {
            throw new Scalr_Exception_Core('This Operating system can\'t be removed');
        }

        if ($os->getUsed()) {
            throw new Scalr_Exception_Core('Operating system is in use and can\'t be removed');
        }
        $os->delete();
        $this->response->success("Operating system successfully removed");
    }

}
