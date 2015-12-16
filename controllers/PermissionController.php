<?php

/*
 * This file is part of the Dektrium project.
 *
 * (c) Dektrium project <http://github.com/dektrium>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace dektrium\rbac\controllers;

use DirectoryIterator;
use Yii;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\web\NotFoundHttpException;

/**
 * @author Dmitry Erofeev <dmeroff@gmail.com>
 */
class PermissionController extends ItemControllerAbstract
{
    /** @var string */
    protected $modelClass = 'dektrium\rbac\models\Permission';
    
    /** @var int */
    protected $type = Item::TYPE_PERMISSION;

    /** @inheritdoc */
    protected function getItem($name)
    {
        $role = Yii::$app->authManager->getPermission($name);

        if ($role instanceof Permission) {
            return $role;
        }

        throw new NotFoundHttpException;
    }
    
    
    private function getControllerActions($controller, $namespace)
    {
        
        $baseName     = pathinfo($controller, PATHINFO_FILENAME);
        $controllerId = str_replace('Controller', '', $baseName);
        
        $className = $namespace.'\\'.$baseName;
        $methods = get_class_methods($className);
        $actions  = [];
        foreach ($methods as $method) {
            $matches  = [];
            if (!preg_match('/^action([^s][\w]+)/', $method, $matches) && !isset($matches[1])) {
                continue;
            }
            $actions[] = lcfirst($controllerId) . '.' . lcfirst($matches[1]);
        }

        return $actions;
    }

    private function getActions()
    {
        $actions = [];
        foreach ($this->module->namespacesToScan as $namespace) {
            $path = Yii::getAlias('@webroot');
            $path = $path.'/../../'.str_replace('\\', '/', $namespace);
            foreach (new DirectoryIterator($path) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                $pathName = $fileInfo->getPathname();
                if (substr($pathName, -14) != 'Controller.php') {
                    return array();
                }
                $actions = array_merge($actions, $this->getControllerActions($pathName, $namespace));
            }
        }
        return $actions;
    }

    private function insertPermision($actions)
    {
        $auth = Yii::$app->authManager;
        foreach($actions as $action) {
            $permission = $auth->createPermission($action);
            $auth->add($permission);
        }
    }

    public function actionScan()
    {
        // iterate over admin controllers/modules and it's actions
        $actions = $this->getActions();

        $permissions     = Yii::$app->authManager->getPermissions();
        $permissionNames = [];
        foreach ($permissions as $permission) {
            $permissionNames[] = $permission->name;
        }

        $permissionsToInsert = array_diff($actions, $permissionNames);

        $this->insertPermision($permissionsToInsert);
        
        Yii::$app->session->setFlash('info', 'Inserted '.count($permissionsToInsert).' new permissions.');
        $this->redirect(['permission/index']);
    }

}