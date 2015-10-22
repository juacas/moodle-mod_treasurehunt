// Standard license block omitted.
/*
 * @package    block_overview
 * @copyright  2015 Someone cool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * @module block_overview/helloworld
  */

define(['jquery','core/notification','core/str'], function($,notification,str) {
 

     var helloworld = {
      init : function(){
           str.get_strings([
                        {'key' : 'hello', component : 'scavengerhunt'},
                        {'key' : 'welcome', component : 'scavengerhunt'}
                    ]).done(function(s) {
                        notification.alert(s[0], s[1], 'Continue');
                    }
                ).fail(notification.exception);
    }
    };
     return helloworld;
 
});

