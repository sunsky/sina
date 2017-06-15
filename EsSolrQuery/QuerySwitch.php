<?php
class QuerySwitch{
    const SOLR = 'solr';
    const ES   = 'es';
    protected  static $flag = self::SOLR;
    public static function setSolrQuery(){
        self::$flag=self::SOLR;
    }

    public static function setESQuery(){
        self::$flag=self::ES;
    }

    public static function getQueryStat(){
        return self::$flag;
    }
}


/**
 * 不在类里边的些独立的函数
 */
function _and($expr0,$expr1){
    return CDoc_LogicalExpression::createAnd(func_get_args());
}

function _or($expr0,$expr1){
    return CDoc_LogicalExpression::createOr(func_get_args());
}

function _not($expr){
    return CDoc_LogicalExpression::createNot($expr);
}

function _eq($fieldname, $value){
    return CDoc_LogicalExpression::createEq($fieldname, $value);
}

function _gt($fieldname, $value){
    return CDoc_LogicalExpression::createGt($fieldname,$value);
}

function _lt($fieldname, $value){
    return CDoc_LogicalExpression::createLt($fieldname,$value);
}

function _gteq($fieldname, $value){
    return CDoc_LogicalExpression::createGteq($fieldname,$value);
}

function _lteq($fieldname, $value){
    return CDoc_LogicalExpression::createLteq($fieldname,$value);
}

function _match($fieldname, $value){
    return CDoc_LogicalExpression::createMatch($fieldname,$value);
}

function _in($fieldname, $values){
    return CDoc_LogicalExpression::createIn($fieldname,$values);
}

function _notnull($fieldname){
    return CDoc_LogicalExpression::createNotnull($fieldname);
}