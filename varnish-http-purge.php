<?php
/*
Plugin Name: Varnish HTTP Purge
Plugin URI: http://wordpress.org/extend/plugins/varnish-http-purge/ 
Description: Sends HTTP PURGE requests to URLs of changed posts/pages when they are modified. 
Version: 3.0
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

class VarnishPurger {
    protected $purgeUrls = array();
    
    public function __construct() {
        add_action( 'init', array( &$this, 'init' ) );
    }
    
    public function init() {
        foreach ($this->getRegisterEvents() as $event) {
            add_action( $event, array($this, 'purgePost') );
        }
        add_action( 'shutdown', array($this, 'executePurge') );
        
        add_action( 'admin_bar_menu', array( $this, "varnish_links" ), 100 );
        
        if ( isset($_GET['vhp_flush_all']) && current_user_can('manage_options') && check_admin_referer('helf_vhp') ) {
            add_action( 'admin_notices', array($this, 'executePurge') );
            add_action( 'admin_notices' , array( $this, 'purgeMessage'));
        }
    }

    function purgeMessage() {
        echo "<div id='message' class='updated fade'><p><strong>".__('Varnish purge flushed!', helf_vhp)."</strong></p></div>";
    }
    
    function varnish_links() {
        global $wp_admin_bar;
          if ( !is_super_admin() || !is_admin_bar_showing() || !is_admin() )
        return;

        $url = wp_nonce_url(admin_url('?vhp_flush_all'), 'helf_vhp');
    
        /* Add the main siteadmin menu item */
        $wp_admin_bar->add_menu( array( 'id' => 'varnish_text', 'title' => __( 'Purge Varnish', helf_vhp ), 'href' => $url ) );
    }

    protected function getRegisterEvents() {
        return array(
            'publish_post',
            'edit_post',
            'deleted_post',
            'switch_theme',
            'delete_attachment'
        );
    }

    public function executePurge() {
        $purgeUrls = array_unique($this->purgeUrls);

        foreach($purgeUrls as $url) {
            $this->purgeUrl($url);
        }
        
        if (!empty($purgeUrls)) {
            $this->purgeUrl(home_url());
        }        
    }

    protected function purgeUrl($url) {
        // Parse the URL for proxy proxies
        $p = parse_url($url);
        $purgehost = $p['host'];

        // Define a ship
        if ( !defined( 'VHP_VARNISH_IP' ) && VHP_VARNISH_IP ) {
            $varniship = get_option('vhp_varnish_ip');
        } else {
            $varniship = VHP_VARNISH_IP;
        }

        // If we set varniship, let it sail
        if ( isset($varniship) ) {
            $purgeme = $p['scheme'].'://'.$varniship.''.$p['path'];
        } else {
            $purgeme = $url;
        }
    
        // Cleanup CURL functions to be wp_remore_request and thus better
        // http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin
        wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => array( 'host' => $purgehost) ) );
    }

    public function purgePost($postId) {
        array_push($this->purgeUrls, get_permalink($postId));
    }

}

$purger = new VarnishPurger();