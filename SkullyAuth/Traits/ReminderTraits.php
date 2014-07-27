<?php
/*-------------------------------------------------\
 * Created by TrioDesign Team (jay@tgitriodesign.com).
 * Date: 2/20/14
 * Time: 10:47 AM
 * 
 \------------------------------------------------*/

namespace App\Controllers;

use App\Helpers\EmailHelper;
use \RedBean_Facade as R;

class ReminderTraits extends BaseController{

    public function index(){
        $this->render("index");
    }

    public function process(){

        $email = $this->getParam("email");
        if(empty($email)){
            echo json_encode(array(
                "errors" => $this->app->getTranslator()->translate("mustExists", array("varStr" => "email")),
                "params" => $this->getParams()
            ));
            return false;
        }

        /** @var \Redbean_SimpleModel $userBean */
        $userBean = R::findOne('user', "email=?", array($email));
        if(empty($userBean)){
            echo json_encode(array(
                "errors" => $this->app->getTranslator()->translate("modelErrorUserNotFound", array($email)),
                "params" => $this->getParams()
            ));
            return false;
        }

        /** @var \App\Models\User $user */
        $user = $userBean->box();
        $user->generateActivationKey();

        try{
            R::store($user);

            $emailHelper = new EmailHelper();
            $emailHelper->setApp($this->app);

            if( $emailHelper->SendNewPasswordConfirmation($user) ){
                echo json_encode(array(
                    "message" => $this->app->getTranslator()->translate("emailSent")
                ));
            }
            else{
                echo json_encode(array(
                    "errors" => $this->app->getTranslator()->translate("unknownError")
                ));
            }
        }
        catch(\Exception $e){
            echo json_encode(array(
                "errors" => $e->getMessage()
            ));
        }
    }

    public function confirm(){
        $result = array();

//        $this->app->getLogger()->log('confirm new password after reset..');
//        $this->app->getLogger()->log("referer : " . (empty($_SERVER["HTTP_REFERER"]) ? " empty referer " : $_SERVER["HTTP_REFERER"]));
        $params = $this->getParams();
        /** @var \Redbean_SimpleModel $userBean */
        $userBean = R::findOne('user', "id = ? and activation_key = ?", array($params["id"], $params["activation_key"]));
        if (!empty($params['activation_key']) && !empty($userBean)) {
            /** @var \App\Models\User $user */
            $user = $userBean->box();
            $user->activationKey = '';

            $newPassword = $user->resetPassword();

            try{
                R::store($user);

                $emailHelper = new EmailHelper();
                $emailHelper->setApp($this->app);

                if($emailHelper->SendNewPassword($user, $newPassword)){
                    $result = array(
                        "success" => 1,
                        "msg" => $this->app->getTranslator()->translate("emailSent")
                    );
                }
                else{
                    $result = array(
                        "success" => 0,
                        "msg" => $this->app->getTranslator()->translate("unknownError"),
                        "params" => $this->params
                    );
                }
            }
            catch(\Exception $e){
                $result = array(
                    "success" => 0,
                    "msg" => $user->errorMessage(),
                    "params" => $this->params
                );
            }
        }
        else {
            $result = array(
                "success" => 0,
                "msg" => $this->app->getTranslator()->translate("userNotFoundOrInvalidActivationKey"),
                "params" => $this->params
            );
        }
        $this->render("success", array(
            "result" => $result
        ));
    }
} 