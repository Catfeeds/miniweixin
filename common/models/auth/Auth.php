<?php

namespace common\models\auth;

use Yii;

/**
 * This is the model class for table "auth_item".
 *
 * @property string $name
 * @property integer $type
 * @property string $description
 * @property string $rule_name
 * @property string $data
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $display
 * @property integer $status
 *
 * @property AuthAssignment[] $authAssignments
 * @property AuthRule $ruleName
 * @property AuthItemChild[] $authItemChildren
 */
class Auth extends \yii\db\ActiveRecord
{
    /**
     * Auth type
     */
    const TYPE_ROLE = 1;
    const TYPE_PERMISSION = 2;
    const DISPLAY_ACTIVE = 1;
    const DISPLAY_DELETED = -1;

    const STATUS_ACTIVE = 10;
    const STATUS_DELETED = -1;
    public $_permissions = [];
    private $_displayLabel;
    public $filter;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%auth_item}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'required'],
//            ['name', 'match', 'pattern' => '/^[a-zA-Z0-9_-]+$/'],
            ['name', 'string', 'min' => 1, 'max' => 64],
            ['name', 'validatePermission'],

            ['description', 'string', 'min' => 1, 'max' => 400],
            ['display', 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('app', 'Name'),
            'description' => Yii::t('app', 'Description'),
            'display' => Yii::t('app', '状态'),
        ];
    }
    public function getDisplayLabel()
    {
        if ($this->_displayLabel === null) {
            $display = self::dislabels();

            $this->_displayLabel = array_key_exists($this->display,self::dislabels()) ? $display[$this->display] : '无';
        }
        return $this->_displayLabel;
    }
    public static function dislabels()
    {
        return [
            self::DISPLAY_ACTIVE=>"启用",
            self::DISPLAY_DELETED => "停用",
        ];
    }
    public static function getArrayStatus()
    {
        return [
            self::STATUS_ACTIVE => Yii::t('app', '正常'),
            self::STATUS_DELETED => Yii::t('app', '回收站'),

        ];
    }
    public function validatePermission()
    {
        if (!$this->hasErrors()) {
            $auth = Yii::$app->getAuthManager();
            if ($this->isNewRecord && $auth->getPermission($this->name)) {
                $this->addError('name', Yii::t('auth', 'This name already exists.'));
            }
            if ($this->isNewRecord && $auth->getRole($this->name)) {
                $this->addError('name', Yii::t('auth', 'This name already exists.'));
            }
        }
    }

    public function createPermission()
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $permission = $auth->createPermission($this->name);
            $permission->description = $this->description;
            $auth->add($permission);

            $admin = $auth->getRole('admin');
            return $auth->addChild($admin, $permission);
        }
        return false;
    }

    public function updatePermission($name)
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $permission = $auth->getPermission($name);
            $permission->description = $this->description;
            return $auth->update($name, $permission);
        }
        return false;
    }

    public function createRole($permissions)
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $role = $auth->createRole($this->name);
            $role->description = $this->description;
            if ($auth->add($role)) {
                foreach ($permissions as $permission) {
                    $obj = $auth->getPermission($permission);
                    $auth->addChild($role, $obj);
                }
                return true;
            }
        }
        return false;
    }

    public function updateRole($name, $permissions)
    {
        if ($this->validate()) {
            $auth = Yii::$app->getAuthManager();
            $role = $auth->getRole($name);
            $role->description = $this->description;
            // save role
            if ($auth->update($name, $role)) {
                // remove old permissions
                $oldPermissions = $auth->getPermissionsByRole($name);
                foreach($oldPermissions as $permission) {
                    $auth->removeChild($role, $permission);
                }

                // add new permissions
                foreach ($permissions as $permission) {
                    $obj = $auth->getPermission($permission);
                    $auth->addChild($role, $obj);
                }
                return true;
            }
        }
        return false;
    }

    public function loadRolePermissions($name) {
        $models = Yii::$app->authManager->getPermissionsByRole($name);
        foreach($models as $model) {
            $this->_permissions[$model->name] = $model->name;
        }
    }

    public static function hasUsersByRole($name) {
        $tablePrefix = Yii::$app->getDb()->tablePrefix;
        return Auth::find()
                ->where(['name' => $name])
                ->leftJoin("{$tablePrefix}auth_assignment", ['item_name' => $name])
                ->count();
    }

    public static function hasRolesByPermission($name) {
        $tablePrefix = Yii::$app->getDb()->tablePrefix;
        return Auth::find()
                ->where(['name' => $name])
                ->leftJoin("{$tablePrefix}auth_item_child", ['child' => $name])
                ->count();
    }
}
