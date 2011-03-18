<?php

function pageflow_submit_tag($flow,$event=false,$value = 'Save changes', $options = array())
{
    $html = 
        input_hidden_tag('sf_pageflow_ticket',$flow->getTicket()).
        submit_tag($value,$options);
    if($event !== false){
        $html = input_hidden_tag('sf_pageflow_event',$event).$html;
    }
    return $html;
}

function pageflow_submit_image_tag($flow, $event=false,$source, $options = array()){
    $html = 
        input_hidden_tag('sf_pageflow_ticket',$flow->getTicket()).
        submit_image_tag($source,$options);
    if($event !== false){
        $html = input_hidden_tag('sf_pageflow_event',$event).$html;
    }
    return $html;
}

function pageflow_link_to($flow, $event=false, $name = '', $internal_uri = '', $options = array())
{
    return link_to($name,pageflow_append_uri_params($flow,$event,$internal_uri),$options);
}

function pageflow_url_for($flow, $event=false, $internal_uri, $absolute = false)
{
    return url_for(pageflow_append_uri_params($flow,$event,$internal_uri),$absolute);
}

function pageflow_append_uri_params($flow,$event=false,$internal_uri){
    return sfPageFlow::appendUriParams($flow,$event,$internal_uri);
}