<?php

use Dba\Connection;

class Backend extends Service {

    private ?PDO $databaseConnection = null;
    private string $databaseCommand;
    private string $databaseDsn;


    public function __construct(string $name, ?string $owner = "www-data"){
        
        parent::__construct($name,$owner);
        $this->setDatabaseDsn();
        $this->setDatabaseCommand();
        $this->waitForDatabaseService();

    }

    protected const TEMPLATE_PATHS = [
        "/usr/local/aspen-discovery/sites/template.linux/conf/badBotsLocal.conf",
        "/usr/local/aspen-discovery/sites/template.linux/conf/config.cron.ini",
        "/usr/local/aspen-discovery/sites/template.linux/conf/config.ini",
        "/usr/local/aspen-discovery/sites/template.linux/conf/config.pwd.ini.template",
        "/usr/local/aspen-discovery/sites/template.linux/conf/crontab_settings.txt",
        "/usr/local/aspen-discovery/docker/files/php_fpm/php-fpm.conf"
    ];

    private function setDatabaseDsn(): void {
        try {
            $databaseDsn = "mysql:" .
            "host=" . $this->getConfig('databaseHost') .
            ";port=" . $this->getConfig('databasePort') .
            ";dbname=" . $this->getConfig('databaseName');
        } catch (ServiceException $e) {
            $this->log("Failed to set the database DSN", 'ERROR');
            throw $e;
        }
    
        $this->databaseDsn = $databaseDsn;
    }

    protected function getDatabaseDsn(): string {
        return $this->databaseDsn;
    }

    private function setDatabaseCommand(): void {
        try {
            $connectionCommand = "mariadb " . 
            "-u" . $this->getConfig('databaseUser') .
            " -p" . $this->getConfig('databasePassword') .
            " -h" . $this->getConfig('databaseHost') .
            " -P" . $this->getConfig('databasePort');
        } catch (ServiceException $e) {
            $this->log("Failed to set the database command", "ERROR");
            throw $e;
        }

        $this->databaseCommand = $connectionCommand;
    }

    protected function getDatabaseCommand(): string {
        return $this->databaseCommand;
    }

    protected function getDatabaseConnection() : ?PDO {

        return $this->databaseConnection;
    }

    protected function afterSteps(){
        $this->checkDirectories();
        $this->checkOwners();
    }

    protected function start(): void {
        
        $response = $this->executeCommand("php-fpm8.4 --test && exec php-fpm8.4 -F");
        $this->log($response['output']);
       
    }

    private function checkDirectories(){
        $this->log("Checking for mandatory directories...");
        try {
            $directories = json_decode(file_get_contents(__DIR__ . "/../directories.json"),true);
            !$directories ?? throw new ServiceException($this->name,$this->getCurrentStep(),"Error: JSON file not found at: " . realpath($directories));

            foreach($directories as $dirName => $dir){
                $pathDir = $this->getMainDefaultDirectories($dirName);
                $this->computeDirectory($dir,$pathDir);
            }
        } catch (ServiceException $e){
            throw $e;
        }
        
    }

    private function computeDirectory(array $dir, string $pathDir): void {
        foreach ($dir as $property => $value) {
            if ($property !== 'owner' && $property !== 'permissions') {
                $fullPath = $pathDir . DIRECTORY_SEPARATOR . $property;

                if (!file_exists($fullPath) || !is_dir($fullPath)) {
                    mkdir($fullPath);
                    $this->log("Created directory: " . $fullPath);
                } else {
                    $this->log("Directory " . $fullPath . " already exists");
                }

                if (isset($value['owner'])) {
                    $this->executeCommand("chown " . $value['owner'] . " " . $fullPath,true,true);
                    $this->log("Owner: " . $value['owner']);
                }

                if (isset($value['permissions'])) {
                    $this->executeCommand("chmod " . $value['permissions'] . " " . $fullPath,true,true);
                    $this->log("Permissions: " . $value['permissions']);
                }

                if (is_array($value)) {
                    $this->computeDirectory($value, $fullPath);
                }
            }
        }
    }

    private function checkOwners(){

        $this->log("Fixing owners and permissions of main directories...");
        $mainDirectories = $this->getMainDefaultDirectories();
        foreach($mainDirectories as $mainDirectory){
            $this->executeCommand("chown -R " . $this->owner . " " . $mainDirectory,true,true);
            $this->executeCommand("chmod -R 755 " . $mainDirectory, true,true);
        }
    }

///////////////////////////// STEPS //////////////////////////////////////////
    public function step_setTemplates(): void {

        $this->log("Processing templates");
        $templatePaths = $this->getTemplatesPaths();
        
        foreach($templatePaths as $templatePath){
            $this->processTemplate($templatePath,$this->configDir . "/conf/" . basename($templatePath));
        }
    }

    public function step_setDatabase(): void {

        try {
            $this->waitForDatabaseService();
        } catch (ServiceException $e) {
            throw $e;
        }


        $testStatement = 'SELECT libraryId FROM library LIMIT 1;';
        if (!$this->hasData($testStatement)){
            $sqlFilePath = $this->getMainDefaultDirectories('baseDir') . "/install/aspen.sql";
            $this->loadDefaultDatabase($sqlFilePath);
            $this->setAdminPassword();
            $this->assignSupportCompany();
            $this->closeDatabaseConnection();
        }

    }

    public function step_setILSConfiguration(): void {

        $this->log("Setting ILS Configuration");

        if ($this->getConfig('enableKoha') == 'yes'){
            
            $ilsConfig = [];
            foreach ($this->getConfig() as $key => $config){
                if (str_starts_with($key,"koha") && !empty($config)){
                    $ilsConfig[$key] = $config;
                    
                } elseif (empty($config)){
                    $message = "The variable $key is not set and it is mandatory for setting the ILS up...";
                    throw new ServiceException($this->getName(),$this->getCurrentStep(),$message);
                }
            }
            $this->waitForDatabaseService();
            $testStatement = "SELECT driver FROM account_profiles WHERE driver = 'Koha';";

            if(!$this->hasData($testStatement)){

                $this->log("Loading ILS information to database...");

                $tmp_dir = rtrim(sys_get_temp_dir(), "/");
                $baseDir = $this->getMainDefaultDirectories('baseDir');
                $sitename = $this->getConfig('sitename');
                $this->replaceVariablesInFile($baseDir . "/install/koha_connection.sql",$tmp_dir . "/koha_connection_" . $sitename . ".sql",$this->getConfig());
            } else {
                $this->log("ILS tables has already been initialized");
            }
        } else {
            $this->log("Koha is not enabled");
        }

    }

    private function waitForDatabaseService(): void {
        $tries = 0;
        $this->log("Waiting for database service to be ready...");

        while(true){
            try {
                    $conn = $this->getDatabaseConnection();
                    if ($conn !== null) {
                        $this->log("Database connection already exists");
                        break;
                    }

                    $dsn = $this->getDatabaseDsn();
                    $dbUser = $this->getConfig('databaseUser');
                    $dbPassword = $this->getConfig('databasePassword');

		            $aspenDatabaseConn = new PDO($dsn, $dbUser, $dbPassword,[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		            $this->databaseConnection = $aspenDatabaseConn;
                    break;

	        } catch (PDOException $e) {
	        		if ($tries == 5){
                        throw new ServiceException($this->getName(),$this->getCurrentStep(),$e->getMessage());
	        		}
	        		$tries++;
                    $this->log("Trying to connect to the database. Try ". $tries);
	        		sleep(5);
	        }
        }
    }

    private function hasData($testStatement){
        $isAlreadyInitialized = false;
        
        $conn = $this->getDatabaseConnection();
        $preparedStatement = $conn->prepare($testStatement);
        try{
            $isAlreadyInitialized = $preparedStatement->execute();
            $this->log("The database has already been initialized");
        } catch (PDOException $e) {
            $this->log("The database is empty. Initializing..."); 
        } finally {
            return $isAlreadyInitialized;
        }
    }

    private function loadDefaultDatabase(string $sqlFilePath): void {
        $this->log("Loading default database...");
        try {
            $this->executeCommand($this->getDatabaseCommand() . " " . $this->getConfig('databaseName') . " < " . $sqlFilePath);
            $this->log("The default database has been successfully loaded");
        } catch (ServiceException $e){
            $this->log("The database could not be loaded",'ERROR');
            throw $e;
        }
    }

    private function setAdminPassword(): void {
        $databaseConn = $this->getDatabaseConnection();
        try {
                $aspenAdminPassword = $this->getConfig('aspenAdminPassword');
                $query = "UPDATE user SET cat_password=" . $databaseConn->quote($aspenAdminPassword) . ", password=" .
                $databaseConn->quote($aspenAdminPassword) . " WHERE username = 'aspen_admin'";

                $statement = $databaseConn->prepare($query);
                $statement->execute();
        } catch (ServiceException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw new ServiceException($this->name,$this->getCurrentStep(),$e->getMessage());
        }
    }

    private function assignSupportCompany() {
        $databaseConn = $this->getDatabaseConnection();
        try {
            $supportingCompany = $this->getConfig('supportingCompany');
            $query = "UPDATE system_variables SET supportingCompany=" . $databaseConn->quote($supportingCompany);
            $statement = $databaseConn->prepare($query);
            $statement->execute();
        } catch (ServiceException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw new ServiceException($this->name,$this->getCurrentStep(),$e->getMessage());
        }
    }

    protected function closeDatabaseConnection(): void {
        $this->databaseConnection = null;
    }

    /*public function step_exit(): void {
        $this->log("The new design works properly");
        exit(0);
    }*/
}