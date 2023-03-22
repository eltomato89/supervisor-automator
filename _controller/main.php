<?php
    require(__DIR__ . "/vendor/autoload.php");
    define('FUNCTIONS_DIR', "/functions");
    define('CONFD_DIR', "/etc/supervisor/conf.d");

    use Monolog\Logger;
    use Monolog\Formatter\LineFormatter;
    use Monolog\Handler\StreamHandler;

    class PHPFunctionServer {

        private $log;
        public function __construct() {

            $this->log = new Logger('PHPFunctionServer');
            
            $output = "[%datetime%] %channel%.%level_name%: %message%\n";
            $formatter = new LineFormatter($output);
            
            $streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
            $streamHandler->setFormatter($formatter);
            $this->log->pushHandler($streamHandler);
            
            $this->log->info("PHP Function Server v0.1");
        }

        public function main() {

            $cachedFiles = [];
            $hashes = [];

            $eventListeners = [
                
            ];
            
            while(true) {

                $currentFiles = array_map('basename', glob(FUNCTIONS_DIR . "/*.{php,phar}", GLOB_BRACE));
                
                $missingFiles = array_diff($cachedFiles, $currentFiles);
                $newFiles = array_diff($currentFiles, $cachedFiles);
                
                //Remove jobs for recently removed (or renamed) php files
                foreach($missingFiles as $file) {
                    $funcName = pathinfo($file)["filename"];
                    $this->log->info("removing job for file " . $funcName);
                    @unlink(CONFD_DIR . "/" . $funcName . ".ini");

                    $this->log->info("rereading supervisor config ...");
                    $this->supervisorRereadConfig();

                    $this->log->info("updating supervisor config ...");
                    $this->supervisorUpdateConfig();
                }

                foreach($newFiles as $file) {
                    //parse new files ...
                    $this->log->info("adding job for file " . $file);

                    //Adding it to the hash array with "wrong" has triggers (re)adding the new file!
                    $hashes[$file] = null;
                }

                foreach($currentFiles as $file) {
                    if( !isset($hashes[$file]) || hash_file("md5", FUNCTIONS_DIR . "/" . $file) != $hashes[$file]) {
                        
                        $this->log->info("Updating job for file " . $file);

                        $funcName = pathinfo($file)["filename"];
                        
                        //Extract Supervisord config from function
                        //sorry, mom!
                        $conf = unserialize(shell_exec("php -r \"include '" . FUNCTIONS_DIR . "/" . $file . "'; echo serialize(" . $funcName . "::config());\""));
                        
                        //Build Supervisor Config File
                        $sconf = "[program:{$funcName}]\n";
                        
                        //(Working) Directory direction needs to be before the command directory!
                        // see https://serverfault.com/a/834182
                        if(isset($conf["directory"])) {
                            $sconf .= "directory = " . $conf["directory"] . "\n";
                        } else {
                            $sconf .= "directory = " . realpath(FUNCTIONS_DIR) . "\n";
                        }
                        
                        //If no command is specified in function, use default ...
                        if(!isset($conf["command"])) $conf["command"] = "php -r \"include '" . $file . "'; (new " . $funcName . "())->run();\"";
                        
                        //add rest of config
                        foreach($conf as $k => $v) {
                            $sconf .= "$k = $v\n";
                        }
                        
                        //Write ini file for this function
                        file_put_contents(CONFD_DIR . "/" . $funcName . ".ini", $sconf);
                        $hashes[$file] = hash_file("md5", FUNCTIONS_DIR . "/" . $file);
                        
                        //Reconfigure Supervisor
                        $this->log->info("rereading supervisor config ...");
                        $this->supervisorRereadConfig();

                        $this->log->info("updating supervisor config ...");
                        $this->supervisorUpdateConfig();
                        
                        //Restart if necessary
                        if(in_array($this->supervisorGetFunctionState($funcName), ["RUNNING", "ERROR"])) {
                            $this->log->info("restarting supervisor job '$funcName'...");
                            $this->supervisorRestartFunction($funcName);
                        } else {
                            $this->log->info("updated function '" . $funcName . "' was not running, so not restarting supervisor job!");
                        }
                    }
                }
                
                $cachedFiles = $currentFiles;

                sleep(5);
            }
        }

        private function supervisorGetFunctionState($funcName) : string {
            return trim(shell_exec("supervisorctl status " . $funcName . " | awk '{print $2}'"));
        }

        private function supervisorRestartFunction($funcName) {
            shell_exec("supervisorctl restart " . $funcName);
        }

        private function supervisorUpdateConfig() {
            shell_exec("supervisorctl update");
        }

        private function supervisorRereadConfig() {
            shell_exec("supervisorctl reread");
        }
        
    }
    (new PHPFunctionServer())->main();
