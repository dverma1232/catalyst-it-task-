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
