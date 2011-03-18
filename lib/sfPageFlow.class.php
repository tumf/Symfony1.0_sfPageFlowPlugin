<?php
/**
 * This file is part of the symfony package.
 * Copyright (c) 2007 Dino Co.,Ltd. 
 *
 * @package    symfony
 * @subpackage plugin
 * @author     Yoshihiro TAKAHARA <takahara@dino.co.jp>
 * @version    SVN: $Id$
 */

class sfPageFlow
{
    protected $config = array();
    protected static $ticket;
    protected $action;
    protected $root;
    
    public function __construct($config,$action,$root)
    {
        $this->action = $action;
        $this->root = $root;
        $this->config = $config;
        $state = $this->getState();
        if(!$state){
            $this->setState($this->config["firstState"]); 
            $this->clearData();
        }
        $this->updateTtl();
    }
    public static function getInstance($action,$root){
        $obj = new self(self::loadPageFlowConfig(),$action,$root);
        return $obj;
    }
    public static function loadPageFlowConfig($module = null)
    {
        if(!$module){
            $module = sfContext::getInstance()->getModuleName();
        }

        $config = include
            (sfConfigCache::getInstance()->checkConfig
             (sprintf("modules/%s/config/pageflow.yml"
                      ,$module)));
        return $config;
    }
    public static function createTicket(){
        return md5(rand(100000, 999999).time());
    }
    public function getTicket(){
        if(self::$ticket) return self::$ticket;

        $request = sfContext::getInstance()->getRequest();
        $ticket = $request->getParameter("sf_pageflow_ticket");
        sfLogger::getInstance()
            ->debug(sprintf("{%s} rerequest ticket is $ticket.",__CLASS__));
        if(strlen($ticket) == 32){
            self::$ticket = $ticket;
        }else{
            sfLogger::getInstance()
                ->debug(sprintf("{%s} rerequest ticket is invalid $ticket.",__CLASS__));
            self::$ticket = self::createTicket();
            sfLogger::getInstance()
                ->debug(sprintf("{%s} new ticket is %s creaeted.",
                                __CLASS__,self::$ticket));
        }
        return self::$ticket;
    }
    public function getNamespace()
    {
        return sprintf("%s-%s-%s"
                       ,"sf_pageflow"
                       ,sfContext::getInstance()->getModuleName()
                       ,$this->getTicket());
    }

    public function getState(){
        return sfContext::getInstance()->getUser()
            ->getAttribute("state",null,$this->getNamespace());
    }
    public function setState($state){
        sfContext::getInstance()->getUser()
            ->setAttribute("state",$state,$this->getNamespace());
    }
    public function getErrors(){
        return sfContext::getInstance()->getUser()
            ->getAttribute("errors",null,$this->getNamespace());
    }
    public function setErrors($errors){
        sfContext::getInstance()->getUser()
            ->setAttribute("errors",$errors,$this->getNamespace());
    }

    /**
     * transit by event
     */
    public function transit($event){
        $event = strtolower($event);
        $request = sfContext::getInstance()->getRequest();

        if(!$this->hasEvent($event)){
            $state = $this->getState();
            $this->reset();
            throw new sfException
                (sprintf("Unknown State '%s' of event '%s'",$state,$event));
        }

        foreach($this->getExitActions() as $fname => $params){
            $this->action->$fname($params["params"]);
        }

        $fromActionState = $this->isActionState();
        $this->setState($this->getTransitTo($event));

        $doevent = $this->getDoEvent();
        if($fromActionState && $this->isViewState()){
            if($doevent){
                $this->setErrors($request->getErrors());
                return $this->action->redirect($this->getTransitUrl());
            }
        }
        return $this->getState();
    }
    public function getTransitUrl(){
        return sprintf("%s?%s=%s&%s=%s"
                       ,$this->root
                       ,"sf_pageflow_status"
                       ,self::getShortStateName($this->getState())
                       ,"sf_pageflow_ticket"
                       ,self::getTicket()
                       );
    }
    // DisplayEgg -> egg
    public static function getShortStateName($state){
        $state = sfInflector::tableize($state);
        if(substr($state,0,8) == "display_"){
            return substr($state, 8);
        }
        if(substr($state,0,8) == "process_"){
            return substr($state, 8);
        }
        return $state;
    }
    
    public function transitOn($event){
        return $this->transit("on".$event);
    }
    public function transitOnSuccess(){
        $this->setErrors(null);
        return $this->transitOn("Success");
    }
    public function transitOnError(){
        return $this->transitOn("Error");
    }
    // 

    public function acceptRequests($vars){
        $request = sfContext::getInstance()->getRequest();
        foreach($vars as $var){
            $this->setData($var,$request->getParameter($var));
        }
    }
    public function getTransitTo($event){
        foreach($this->config["state"][$this->getState()]["transition"] as $k => $v){
            if(strtolower($event) == strtolower($k)){
                return $v;
            }
        }
        return null;
    }
    public function hasEvent($event){
        foreach($this->config["state"][$this->getState()]["transition"] as $k => $v){
            if(strtolower($event) == strtolower($k)){
                return true;
            }
        }
        return false;
    }

    public function hasEntryAction(){
        return isset($this->config["state"][$this->getState()]["entry"]);

    }
    public function getEntryActions(){
        if($this->hasEntryAction()){
            return $this->config["state"][$this->getState()]["entry"];
        }
        return array();
    }
    public function hasExitAction(){
        return isset($this->config["state"][$this->getState()]["exit"]);

    }
    public function getExitActions(){
        if($this->hasExitAction()){
            return $this->config["state"][$this->getState()]["exit"];
        }
        return array();
    }

    public function isViewState(){
        return substr($this->getState(),0,7) == "Display";
    }
    public function isActionState(){
        return substr($this->getState(),0,7) == "Process";
    }
    public function reset(){
        sfContext::getInstance()->getUser()
            ->setAttribute("state",null,$this->getNamespace());
    }
    public function resetAndRedirect(){
        $this->reset();
        return $this->action->redirect($this->root);
    }
    public function checkRequest(){
        if($this->isViewState()){
            $url_status = sfContext::getInstance()->getRequest()
                ->getParameter("sf_pageflow_status",null);
            $status = self::getShortStateName($this->getState());
            sfLogger::getInstance()
                ->debug(sprintf("{%s} checkRequest request: $url_status status: $status",__CLASS__));
            return $url_status == $status?true:false;
        }
    }
    public function doEvent(){
        $event = $this->getDoEvent();
        if($event && $this->hasEvent($event)){
            $this->transit($event);
        }
    }
    public function getDoEvent(){
        return strtolower
            (sfContext::getInstance()->getRequest()
             ->getParameter("sf_pageflow_event",null));
    }
    public function processAction(){
        $module =  sfContext::getInstance()->getModuleName();
        foreach($this->getEntryActions() as $fname => $params){
            $this->action->$fname($params["params"]);
        }

        if( $this->isActionState() ){
            $action_name = sfInflector::underscore(substr($this->getState(),7));
        }else{
            if($errors = $this->getErrors()){
                sfContext::getInstance()->getRequest()->setErrors($errors);
            }

            $action_name = "display";
        }
        $action_name = sfInflector::camelize($action_name);
        $action_name{0} = strtolower($action_name{0});
        $this->action->forward($module,$action_name);
    }

    public function display(){
        if(!$this->checkRequest()){
            return $this->resetAndRedirect();
        }
        if(sfContext::getInstance()->getRequest()
           ->getParameter("sf_pageflow_ticket") == "new"){
            return $this->action->redirect($this->getTransitUrl());
        }
        if($this->isViewState()){
            $view =  substr($this->getState(),7);
        }
        if($this->isLastState()){
            $this->reset();
        }
        return $view;

    }

    public function isLastState(){
        return ($this->config["lastState"] == $this->getState());
    }
    public function execute()
    {
        $this->processAction();
    }
    public function getData($var,$default = null){
        $ns = $this->getNamespace()."-data";
        return sfContext::getInstance()->getUser()->getAttribute($var,$default,$ns);
    }
    public function getAll(){
        $ns = $this->getNamespace()."-data";
        return sfContext::getInstance()->getUser()->getAttributeHolder()->getAll($ns);
    }
    public function setData($var,$val){
        $ns = $this->getNamespace()."-data";
        sfContext::getInstance()->getUser()->setAttribute($var,$val,$ns);
    }

    public function clearData(){
        $ns = $this->getNamespace()."-data";
        sfContext::getInstance()->getUser()->getAttributeHolder()->removeNamespace($ns);
    }
    public function hasActionState($name){
        return $this->hasState("Process".$name);
    }
    public function hasViewState($name){
        return $this->hasState("Display".$name);
    }
    public function hasState($name){
        return array_key_exists($name,$this->config["state"]);
    }
    public function updateTtl(){
        if(isset($this->config["ttl"]) && $this->config["ttl"] > 1){
            $this->setTtl(time() + $this->config["ttl"]);
        }
    }
    public function checkTtl(){
        return true;
    }
    public function getTtl(){
        return sfContext::getInstance()->getUser()
            ->getAttribute("ttl",null,$this->getNamespace());
    }
    public function setTtl($ttl){
        sfContext::getInstance()->getUser()
            ->setAttribute("ttl",$ttl,$this->getNamespace());
    }

    static public function appendUriParams($flow,$event=false,$internal_uri){
        if(strpos($internal_uri,"?")){
            $internal_uri .= "&";
        }else{
            $internal_uri .= "?";
        }
        $internal_uri .= "sf_pageflow_ticket=".$flow->getTicket();
        $internal_uri .= "&sf_pageflow_status=".self::getShortStateName($flow->getState());
        if($event !== false){
            $internal_uri .= "&sf_pageflow_event=".$event;
        }
        return $internal_uri;
    }
}
