<?php

$options = getopt("u:p:h:", ["file:", "create_table", "dry_run", "help"]);
$dbConfig = [
    'user' => $options['u'] ?? '',
    'pass' => $options['p'] ?? '',
    'host' => $options['h'] ?? 'localhost',
];

function showHelp() {
    echo "Usage:\n";
    echo "--file [csv file name] - Name of the CSV to be parsed\n";
    echo "--create_table - Builds the MySQL users table and exits\n";
    echo "--dry_run - Runs the script but does not alter the database\n";
    echo "-u - MySQL username\n";
    echo "-p - MySQL password\n";
    echo "-h - MySQL host\n";
    echo "--help - Outputs this list of directives\n";
}

if (array_key_exists('help', $options)) {
    showHelp();
    exit;
}

function connectDatabase($dbConfig) {
    try {
        $pdo = new PDO("mysql:host={$dbConfig['host']}", $dbConfig['user'], $dbConfig['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("DB Connection failed: " . $e->getMessage());
    }
}

function createTable($pdo) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS catalyst");
    $pdo->exec("USE catalyst");
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        surname VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $pdo->exec($sql);
}

if (array_key_exists('create_table', $options)) {
    $pdo = connectDatabase($dbConfig);
    createTable($pdo);
    echo "Table 'users' created successfully.\n";
    exit;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function processCSV($filename, $pdo, $dryRun = false) {
    if (!file_exists($filename) || !is_readable($filename)) {
        echo "CSV file is not readable\n";
        exit;
    }

    $header = null;
    $data = array();

    if (($handle = fopen($filename, 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if (!$header) {
                // Trim to remove whitespace
                $header = array_map('trim', $row);
            } else {
                $rowData = array_combine($header, array_map('trim', $row));
                $rowData['name'] = ucfirst(strtolower($rowData['name']));
                $rowData['surname'] = ucfirst(strtolower($rowData['surname']));
                $rowData['email'] = strtolower($rowData['email']);

                if (!validateEmail($rowData['email'])) {
                    echo "Invalid email format: {$rowData['email']}\n";
                    continue;
                }

               if (!$dryRun) {
                    $sql = "INSERT INTO catalyst.users (name, surname, email) VALUES (:name, :surname, :email)";
                    $stmt = $pdo->prepare($sql);
                    try {
                        $stmt->execute([
                            ':name' => $rowData['name'],
                            ':surname' => $rowData['surname'],
                            ':email' => $rowData['email']
                        ]);
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // 23000 is SQL state code for integrity constraint violation
                            echo "Duplicate email found, skipping: {$rowData['email']}\n";
                        } else {
                            throw $e;
                        }
                    }
                }

                echo "Processed: {$rowData['email']}\n";
            }
        }
        fclose($handle);
    }
}

if (isset($options['file'])) {
    $pdo = connectDatabase($dbConfig);
    processCSV($options['file'], $pdo, array_key_exists('dry_run', $options));
}
