<?php
/*
Plugin Name: Varnish HTTP Purge
Plugin URI: http://wordpress.org/extend/plugins/varnish-http-purge/ 
Description: Sends HTTP PURGE requests to URLs of changed posts/pages when they are modified. 
Version: 2.2b
Author: Mika Epstein
Author URI: http://halfelf.org/
License: http://www.apache.org/licenses/LICENSE-2.0

Original Author: Leon Weidauer ( http:/www.lnwdr.de/ )

Copyright 2013: Mika A. Epstein (email: ipstenu@ipstenu.org)

    This file is part of Varnish HTTP Purge, a plugin for WordPress.

    Varnish HTTP Purge is free software: you can redistribute it and/or modify
    it under the terms of the Apache License 2.0 license.

    Varnish HTTP Purge is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

*/

class VarnishPurger
{
    protected $purgeUrls = array();
    
    public function __construct()
    {
        foreach ($this->getRegisterEvents() as $event)
        {
            add_action($event, array($this, 'purgePost'));
        }
        add_action('shutdown', array($this, 'executePurge'));
    }

    protected function getRegisterEvents()
    {
        return array(
            'publish_post',
            'edit_post',
            'deleted_post',
        );
    }

    public function executePurge()
    {
        $purgeUrls = array_unique($this->purgeUrls);

        foreach($purgeUrls as $url)
        {
            $this->purgeUrl($url);
        }
        
        if (!empty($purgeUrls))
        {
            $this->purgeUrl(home_url());
        }        
    }

    protected function purgeUrl($url)
    {

        // Check to see if we've defined a varnish IP and set the variable if so.
        if ( !get_option('vhp_varnish_ip') ) {
            if ( !defined( 'VHP_VARNISH_IP' ) && VHP_VARNISH_IP ) { $purgeme = home_url(); }
            else { $varniship = VHP_VARNISH_IP; }
        } elseif (get_option('vhp_varnish_ip')) { 
            $varniship = get_option('vhp_varnish_ip');
        } 
        
        // If we set varniship, we should use it.
        // curl -H "Host: realdomain.com" http://1.2.3.4/path/foo
        $p = parse_url($url);
        $purgehost = $p['host'];
        if ( isset($varniship) ) {
            // THIS MAY NOT BE RIGHT!
            $purgeme = $p['scheme'].'://'.$varniship.'/'.$p['path'];
        } else {
            $purgeme = $url;
        }
    
        // Cleanup CURL functions to be wp_remore_request and thus better
        // http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin
        
        // URL MAY NOT BE RIGHT!
        wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => array( 'host' => $purgehost) ) );
    }

    public function purgePost($postId)
    {
        array_push($this->purgeUrls, get_permalink($postId));
    }
}

$purger = new VarnishPurger();