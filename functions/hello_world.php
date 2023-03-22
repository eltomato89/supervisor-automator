<?php

    class hello_world {

        public static function config() {
            return [
                "autostart" => "false",
                "autorestart" => "true",
                "redirect_stderr" => "true"
            ];
        }
        
        public function __construct() {
            
        }
        
        public function run() {
            while(true) {
            echo "Hello World!\n";
            sleep(5);
            }
        }
               

    }
