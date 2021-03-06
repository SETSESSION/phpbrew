<?php
namespace PhpBrew\Command;
use PhpBrew\Config;

class InitCommand extends \CLIFramework\Command
{
    public function brief() { return 'Initialize phpbrew config file.'; }

    public function execute()
    {
        // $currentVersion;
        $root = Config::getPhpbrewRoot();
        $home = Config::getPhpbrewHome();
        $buildDir = Config::getBuildDir();
        $buildPrefix = Config::getBuildPrefix();
        // $versionBuildPrefix = Config::getVersionBuildPrefix($version);
        // $versionBinPath     = Config::getVersionBinPath($version);

        if( ! file_exists($root) )
            mkdir( $root, 0755, true );

        if( ! file_exists($buildPrefix) )
            mkdir( $buildPrefix, 0755, true );

        if( ! file_exists($buildDir) )
            mkdir( $buildDir, 0755, true );

        // write init script
        $bashScript = $root . DIRECTORY_SEPARATOR . 'bashrc';
        // $initScript = $root . DIRECTORY_SEPARATOR . 'init';
        file_put_contents( $bashScript , $this->getBashScript() );

        echo <<<EOS
Phpbrew environment is initialized, required directories are created under

    $home

Paste the following line(s) to the end of your ~/.bashrc and start a
new shell, phpbrew should be up and fully functional from there:

    source $home/bashrc

To enable PHP version info in your shell prompt, please set PHPBREW_SET_PROMPT=1
in your `~/.bashrc` before you source `~/.phpbrew/bashrc`

    export PHPBREW_SET_PROMPT=1

For further instructions, simply run `phpbrew` to see the help message.

Enjoy phpbrew at \$HOME!!

EOS;

    }

    public function getBashScript()
    {
        // SHBLOCK {{{
    return <<<'EOS'
#!/bin/bash
# Brought from gugod's perlbrew.
# Author: Yo-An Lin
# NOTICE: This script is for local testing, to release updated script, 
# please also modify the src/PhpBrew/Command/InitCommand.php

# default phpbrew root and phpbrew home path

[[ -z "$PHPBREW_HOME" ]] && export PHPBREW_HOME="$HOME/.phpbrew"

if [[ -z "$PHPBREW_SKIP_INIT" ]]; then
    # load user-defined config
    if [[ -f $PHPBREW_HOME/init ]]; then
        . $PHPBREW_HOME/init
        export PATH=$PHPBREW_PATH:$PATH
    fi
fi

[[ -z "$PHPBREW_ROOT" ]] && export PHPBREW_ROOT="$HOME/.phpbrew"


function phpbrew ()
{
    if [[ -e bin/phpbrew ]] ; then
        BIN='bin/phpbrew'
    else
        BIN='phpbrew'
    fi

    local exit_status
    local short_option
    export SHELL
    if [[ `echo $1 | awk 'BEGIN{FS=""}{print $1}'` = '-' ]]
    then
        short_option=$1
        shift
    else
        short_option=""
    fi


    case $1 in
        use) if [[ -z "$2" ]]
            then
                if [[ -z "$PHPBREW_PHP" ]]
                then
                    echo "Currently using system php"
                else
                    echo "Currently using $PHPBREW_PHP"
                fi
            else
                if [[ $2 =~ ^php- ]]
                then
                    _PHP_VERSION=$2
                else
                    _PHP_VERSION="php-$2"
                fi

                # checking php version exists?
                NEW_PHPBREW_PHP_PATH="$PHPBREW_ROOT/php/$_PHP_VERSION"
                if [ -d $NEW_PHPBREW_PHP_PATH ]; then
                    code=$(command $BIN env $_PHP_VERSION)
                    if [ -z "$code" ]
                    then
                        exit_status=1
                    else
                        eval $code
                        __phpbrew_set_path
                    fi
                else
                    echo "php version: $_PHP_VERSION not exists."
                fi
            fi
            ;;
        switch)
            if [[ -z "$2" ]]
            then
                echo "Please specify the php version."
            else
                __phpbrew_reinit $2
            fi
            ;;
        off)
            unset PHPBREW_PHP
            unset PHPBREW_PATH
            eval `$BIN env`
            __phpbrew_set_path
            echo "phpbrew is turned off."
            ;;
        switch-off)
            unset PHPBREW_PHP
            unset PHPBREW_PATH
            eval `$BIN env`
            __phpbrew_set_path
            __phpbrew_reinit
            echo "phpbrew is switched off."
            ;;
        remove)
            if [[ -z "$2" ]]
            then
                command $BIN help
            else
              __phpbrew_remove_purge $2
            fi
            ;;
        rehash)
            echo "Rehashing..."
            . ~/.phpbrew/bashrc
            ;;
        purge)
            if [[ -z "$2" ]]
            then
                command $BIN help
            else
              __phpbrew_remove_purge $2 purge
            fi
            ;;
        *)
            command $BIN $short_option "$@"
            exit_status=$?
            ;;
    esac
    hash -r
    return ${exit_status:-0}
}

function __phpbrew_set_path ()
{
    [[ -n $(alias php 2>/dev/null) ]] && unalias php 2> /dev/null

    if [[ -n $PHPBREW_ROOT ]] ; then
        export PATH_WITHOUT_PHPBREW=$(perl -e 'print join ":", grep { index($_,$ENV{PHPBREW_ROOT}) } split/:/,$ENV{PATH};')
    fi

    if [[ -z "$PHPBREW_PATH" ]]
    then
        export PHPBREW_PATH="$PHPBREW_ROOT/bin"
    fi
    export PATH=$PHPBREW_PATH:$PATH_WITHOUT_PHPBREW
    # echo "PATH => $PATH"
}

function __phpbrew_reinit () 
{
    if [[ $1 =~ ^php- ]]
    then
        _PHP_VERSION=$1
    else
        _PHP_VERSION="php-$1"
    fi

    if [[ ! -d "$PHPBREW_HOME" ]]
    then
        mkdir -p -p "$PHPBREW_HOME"
    fi
    echo '# DO NOT EDIT THIS FILE' >| "$PHPBREW_HOME/init"
    command $BIN env $_PHP_VERSION >> "$PHPBREW_HOME/init"
    . "$PHPBREW_HOME/init"
    __phpbrew_set_path
}

function __phpbrew_remove_purge ()
{
    if [[ $1 =~ ^php- ]]
    then
        _PHP_VERSION=$1
    else
        _PHP_VERSION="php-$1"
    fi

    if [[ "$_PHP_VERSION" = "$PHPBREW_PHP" ]]
    then
        echo "php version: $_PHP_VERSION is already in used."
        return 1
    fi

    _PHP_BIN_PATH=$PHPBREW_HOME/php/$_PHP_VERSION
    _PHP_SOURCE_FILE=$PHPBREW_HOME/build/$_PHP_VERSION.tar.bz2
    _PHP_BUILD_PATH=$PHPBREW_HOME/build/$_PHP_VERSION

    if [ -d $_PHP_BIN_PATH ]; then

        if [[ "$2" = "purge" ]]
        then
            rm -f $_PHP_SOURCE_FILE
            rm -fr $_PHP_BUILD_PATH
            rm -fr $_PHP_BIN_PATH

            echo "php version: $_PHP_VERSION is removed and purged."
        else
            rm -f $_PHP_SOURCE_FILE
            rm -fr $_PHP_BUILD_PATH

            for FILE1 in $_PHP_BIN_PATH/*
            do
                if [[ "$FILE1" != "$_PHP_BIN_PATH/etc" ]] && [[ "$FILE1" != "$_PHP_BIN_PATH/var" ]]
                then
                    rm -fr $FILE1;
                fi
            done

            echo "php version: $_PHP_VERSION is removed."
        fi

    else
        echo "php version: $_PHP_VERSION not installed."
    fi

    return 0
}
EOS;
// SHBLOCK }}}

    }
}
