<?php
/**
 * This file is part of the symfony package.
 * Copyright (c) 2007 Dino Co.,Ltd. 
 *
 * @package    symfony
 * @subpackage plugin
 * @author     Yoshihiro TAKAHARA <takahara@dino.co.jp
 * @version    SVN: $Id$
 *
 *
 *
 *
 *
 *
 */
class sfPageFlowActions extends sfActions
{

    public function acceptRequests($vars){
        $this->flow->acceptRequests($vars);
    }
    public function executeFlow(){
        $this->flow->doEvent();
        return $this->flow->execute();
    }
    public function executeDisplay(){
        return $this->flow->display();
    }
    // handle validate Error
    public function handleError(){
        $this->flow->transitOnError();
        return $this->flow->execute();
    }
    public function initialize($context){
        $module = sfContext::getInstance()->getModuleName();

        if(parent::initialize($context)){
            $this->flow = sfPageFlow::getInstance($this
                                                  ,sprintf("%s/flow",$module));
            /*
            if($this->flow->getTicket()
               != $this->getRequestParameter("sf_pageflow_ticket")){
                $this->redirect($this->flow->getTransitUrl());
                return false;
            }
            */
            return true;
        }
        return false;
    }

    /*
      I would like to handle some typcal actions.
       but don't work.
    public function __call($name, $arg){
        if(!preg_match("/^execute(.*)/",$name,$m)
           || !$this->flow->hasState($m[1])){
            return parent::__call($name,$arg);
        }
        $this->flow->transitOnSuccess();
        return $this->flow->execute();
    }
    */
}

