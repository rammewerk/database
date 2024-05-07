<?php

namespace Rammewerk\Component\Database;

use LogicException;

readonly class DatabaseBackup {

    public function __construct(
        private string  $host,
        private ?string $database,
        private string  $user,
        private string  $password
    ) {
    }


    /**
     * Backup Database
     *
     * @param string $dir Backup directory
     * @parm string $name Prepended file name / database name
     */
    public function backup(string $dir): void {

        $dir = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

        if( !is_dir( $dir ) ) throw new LogicException( 'Backup folder does not exists' );

        $file = $dir . $this->database . '_' . date( 'Y-m-d_H' ) . '.sql';

        if( is_file( $file ) ) unlink( $file );

        exec( "mysqldump --user=$this->user --password=$this->password --host=$this->host $this->database --result-file=$file 2>&1" );

    }

}