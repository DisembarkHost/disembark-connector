<?php

namespace DisembarkConnector;

class User {

    public static function allowed( $request ) {
        if ( $request['token'] == Token::get() ) {
            return true;
        }
        return false;
    }

}