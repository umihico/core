<?php

namespace tdt\core\datacontrollers;

/**
 * CSV Controller
 * @copyright (C) 2011,2013 by OKFN Belgium vzw/asbl
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 * @author Michiel Vancoillie <michiel@okfn.be>
 */
abstract class ADataController {

    protected static $DEFAULT_PAGE_SIZE = 500;

    public abstract function readData($source_definition, $parameters = null);

     /**
     * Calculate the limit and offset based on the request string parameters.
     */
    protected function calculateLimitAndOffset(){

        $limit = \Input::get('limit', self::$DEFAULT_PAGE_SIZE);
        $offset = \Input::get('offset', 0);

        // Calculate the limit and offset, if only page and optionally page_size are given
        if($limit == self::$DEFAULT_PAGE_SIZE && $offset == 0){

            $page = \Input::get('page', 1);
            $page_size = \Input::get('page_size', self::$DEFAULT_PAGE_SIZE);

            // Don't do extra work when page and page_size are also default values.
            if($page > 1 || $page_size != self::$DEFAULT_PAGE_SIZE){

                $offset = ($page -1)*$page_size;
                $limit = $page_size;
            }else if($page == -1){

                $limit = PHP_INT_MAX;
                $offset= 0;
            }
        }else if($limit == -1){
            $limit = PHP_INT_MAX;
        }

        return array($limit, $offset);
    }
}