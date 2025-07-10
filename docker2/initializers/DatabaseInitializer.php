<?php

/**
 * Database Initializer
 */
class DatabaseInitializer extends InitService {
    private ?PDO $databaseConnection = null;
    private string $databaseCommand;
    private string $databaseDsn;

    public function __construct() {
        parent::__construct('database');
        $this->setDatabaseDsn();
        $this->setDatabaseCommand();
    }

    public function run(): void {
        $this->setCurrentStep('wait_for_database');
        $this->waitForDatabaseService();

        $this->setCurrentStep('check_database_state');
        if (!$this->isDatabaseInitialized()) {
            $this->setCurrentStep('load_schema');
            $this->loadDefaultDatabase();

            $this->setCurrentStep('set_admin_password');
            $this->setAdminPassword();

            $this->setCurrentStep('set_supporting_company');
            $this->assignSupportCompany();
        }

        $this->setCurrentStep('setup_ils');
        $this->setupILSConfiguration();

        $this->closeDatabaseConnection();
    }

    private function setDatabaseDsn(): void {
        $this->databaseDsn = "mysql:" .
            "host=" . $this->getConfig('databaseHost') .
            ";port=" . $this->getConfig('databasePort') .
            ";dbname=" . $this->getConfig('databaseName');
    }

    private function setDatabaseCommand(): void {
        $this->databaseCommand = "mariadb " .
            "-u" . $this->getConfig('databaseUser') .
            " -p" . $this->getConfig('databasePassword') .
            " -h" . $this->getConfig('databaseHost') .
            " -P" . $this->getConfig('databasePort');
    }

    private function waitForDatabaseService(): void {
        $tries = 0;
        $maxTries = 30; // 2.5 minutes max wait
        $this->log("Waiting for database service to be ready...");

        while (true) {
            try {
                if ($this->databaseConnection !== null) {
                    $this->log("Database connection already exists");
                    break;
                }

                $dsn = $this->databaseDsn;
                $dbUser = $this->getConfig('databaseUser');
                $dbPassword = $this->getConfig('databasePassword');

                $this->databaseConnection = new PDO($dsn, $dbUser, $dbPassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $this->log("Database connection established");
                break;

            } catch (PDOException $e) {
                if ($tries >= $maxTries) {
                    throw new InitException($this->mode, $this->currentStep, 
                        "Database connection failed after {$maxTries} attempts: " . $e->getMessage());
                }
                $tries++;
                $this->log("Trying to connect to database. Attempt {$tries}/{$maxTries}");
                sleep(5);
            }
        }
    }

    private function isDatabaseInitialized(): bool {
        $testStatement = 'SELECT libraryId FROM library LIMIT 1;';
        
        try {
            $stmt = $this->databaseConnection->prepare($testStatement);
            $stmt->execute();
            $this->log("Database has already been initialized");
            return true;
        } catch (PDOException $e) {
            $this->log("Database is empty. Initializing...");
            return false;
        }
    }

    private function loadDefaultDatabase(): void {
        $this->log("Loading default database schema...");
        $sqlFilePath = $this->baseDir . "/install/aspen.sql";
        
        if (!file_exists($sqlFilePath)) {
            throw new InitException($this->mode, $this->currentStep, "SQL file not found: {$sqlFilePath}");
        }

        $this->executeCommand($this->databaseCommand . " " . $this->getConfig('databaseName') . " < " . $sqlFilePath);
        $this->log("Default database schema loaded successfully");
    }

    private function setAdminPassword(): void {
        $this->log("Setting admin password...");
        
        $aspenAdminPassword = $this->getConfig('aspenAdminPassword');
        $query = "UPDATE user SET cat_password = ?, password = ? WHERE username = 'aspen_admin'";

        $stmt = $this->databaseConnection->prepare($query);
        $stmt->execute([$aspenAdminPassword, $aspenAdminPassword]);
        
        $this->log("Admin password set successfully");
    }

    private function assignSupportCompany(): void {
        $this->log("Setting supporting company...");
        
        $supportingCompany = $this->getConfig('supportingCompany');
        $query = "UPDATE system_variables SET supportingCompany = ?";

        $stmt = $this->databaseConnection->prepare($query);
        $stmt->execute([$supportingCompany]);
        
        $this->log("Supporting company set successfully");
    }

    private function setupILSConfiguration(): void {
        if ($this->getConfig('enableKoha') !== 'yes') {
            $this->log("Koha is not enabled, skipping ILS configuration");
            return;
        }

        $this->log("Setting up ILS (Koha) configuration...");
        
        // Check if ILS is already configured
        $testStatement = "SELECT driver FROM account_profiles WHERE driver = 'Koha'";
        
        try {
            $stmt = $this->databaseConnection->prepare($testStatement);
            $stmt->execute();
            if ($stmt->fetch()) {
                $this->log("ILS configuration already exists");
                return;
            }
        } catch (PDOException $e) {
            // Table might not exist yet, continue with setup
        }

        // Process Koha configuration template
        $tmpDir = rtrim(sys_get_temp_dir(), "/");
        $sitename = $this->getConfig('sitename');
        $kohaConfigFile = $tmpDir . "/koha_connection_" . $sitename . ".sql";
        
        $this->replaceVariablesInFile(
            $this->baseDir . "/install/koha_connection.sql",
            $kohaConfigFile,
            $this->getConfig()
        );

        // Execute the Koha configuration
        $this->executeCommand($this->databaseCommand . " " . $this->getConfig('databaseName') . " < " . $kohaConfigFile);
        
        // Clean up temporary file
        unlink($kohaConfigFile);
        
        $this->log("ILS configuration completed");
    }

    private function closeDatabaseConnection(): void {
        $this->databaseConnection = null;
        $this->log("Database connection closed");
    }
}
