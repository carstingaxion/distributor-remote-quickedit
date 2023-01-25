# Distributor - Remote Quickedit

Stable tag: 0.1.0  
Requires at least: 5.9  
Tested up to: 6.1.1  
Requires PHP: 7.1  
License: GPL v3 or later  
Tags: distributor, quickedit  
Contributors: carstenbach  

Re-enable quickedit for distributed posts.

## Description

Re-enable quickedit for distributed posts on the receiving site within a multisite network. This allows you to make changes to the original post from the remote site. This is a small add-on for the glorious [Distributor](https://distributorplugin.com/) plugin by *10up*.

This Add-on is maintained at and deployed from [carstingaxion/distributor-remote-quickedit](https://github.com/carstingaxion/distributor-remote-quickedit) on github. 

### Features

 * Use native WordPress quickedit on the receiving side of a distributed post to make minor changes, which is disabled by the *Distributor*-plugin by default.

### Usage

***This plugin does nothing by default.***

In order to re-enable quickedit for a particular post_type you need to `add_post_type_support()` for *`distributor-remote-quickedit`* onto your desired post_type at first. 

You can do it like so:

~~~php
add_action( 'admin_init', function () {
	add_post_type_support( 'post', 'distributor-remote-quickedit' );
}, 9 );
~~~

It's important to declare your post_type_supports before the plugin is executed on `admin_init|10`!

## Frequently Asked Questions

### Does this plugin work with WordPress Multisite?

Yes, it is made for multisites with internal distribution setup.

### The Distributor plugin disables the use of quickedit for reasons. Why would I want to change that?

It totally depends on your use case ;)

In our case, on a large multisite network, there was only one out of almost 20 post_types, that needed this *feature*, for sure - real-world-use-cases may be rare.

<!-- changelog -->
