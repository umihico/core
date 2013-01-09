<?php

/**
 * Executes an Identifier
 *
 * format: 
 * - package.package.resource
 * - package.package.resource.name_of_column
 * - alias.name_of_column
 *
 * @package The-Datatank/universalfilter/interpreter/executers
 * @copyright (C) 2012 by iRail vzw/asbl
 * @license AGPLv3
 * @author Jeroen Penninck
 */

namespace tdt\core\universalfilter\interpreter\executers\implementations;

class IdentifierExecuter extends tdt\core\universalfilter\interpreter\executers\base\AbstractUniversalFilterNodeExecuter {
    
    private $interpreter;
    private $topenv;
    
    private $header;
    
    private $singlevaluecolumnheader;
    private $singlevalueindex;
    
    private $isColumn;
    private $isNewTable;
    
    public function initExpression(tdt\core\universalfilter\UniversalFilterNode $filter, tdt\core\universalfilter\interpreter\Environment $topenv, tdt\core\universalfilter\interpreter\IInterpreterControl $interpreter, $preferColumn) {
        
        $this->filter = $filter;
        $this->interpreter = $interpreter;
        $this->topenv = $topenv;
        
        if($preferColumn){
            $this->isNewTable = false;
            
            $this->isColumn = true;
            $this->header = $this->getColumnDataHeader($topenv, $this->filter->getIdentifierString());
            if($this->header===null){
                throw new Exception("The identifier \"".$this->filter->getIdentifierString()."\" can not be found. It is not a column.");
            }
            if(!$this->isColumn){
                $this->singlevaluecolumnheader=$this->header->getColumnInformationById($this->header->getColumnId());
                $this->header = new tdt\core\universalfilter\data\UniversalFilterTableHeader(array($this->singlevaluecolumnheader->cloneColumnNewId()), true, true);
            }
        }else{           
            $this->isNewTable = true;
            // load new table               
            $tableName = $filter->getIdentifierString();             
            try {
                $this->header = $interpreter->getTableManager()->getTableHeader($tableName);
            } catch(tdt\framework\TDTException $rce){
                throw new tdt\framework\TDTException(500,array("IdentifierExectuer - The identifier \"".$tableName."\" can not be found. It is not a table."));
            }
        }
    }
    
    public function getExpressionHeader(){
        return $this->header;
    }
    
    public function evaluateAsExpression() {
        if($this->isNewTable){
            $tableName = $this->filter->getIdentifierString();
            return $this->interpreter->getTableManager()->getTableContent($tableName, $this->header);
        }else{
            if(!$this->isColumn){
                $newRow = new tdt\core\universalfilter\data\UniversalFilterTableContentRow();
                $value = $this->topenv->getSingleValue($this->singlevalueindex)->copyValueTo($newRow, $this->singlevaluecolumnheader->getId(), $this->header->getColumnId());
                $content = new tdt\core\universalfilter\data\UniversalFilterTableContent();
                $content->addRow($newRow);
                return $content;
            }else{
                return $this->getColumnDataContent($this->topenv->getTable(), $this->filter->getIdentifierString(), $this->header);
            }
        }
    }
    
    /*
     * TOOL METHODS:
     */
    
    /**
     * Get a single column from the data (header)
     * @return UniversalFilterTableHeader
     */
    private function getColumnDataHeader(tdt\core\universalfilter\interpreter\Environment $topenv, $fullid){
        if($fullid=="*"){
            //special case => current table
            return $topenv->getTable()->getHeader()->cloneHeader();
        }
        
        $originalheader = $topenv->getTable()->getHeader();
        $columnid = $originalheader->getColumnIdByName($fullid);
        
        if($columnid===null){
            $this->isColumn=false;//it's a single value...
            
            $foundheader=null;
            
            for ($index = 0; $index < $topenv->getSingleValueCount(); $index++) {
                $columninfo = $topenv->getSingleValueHeader($index);
                
                if($columninfo->matchName(explode(".", $fullid))){
                    if($foundheader!=null){
                        throw new Exception("Ambiguos identifier: \"".$fullid."\". Please use aliases to remove the ambiguity.");//can only occured in nested querys or joins
                    }
                    $this->singlevalueindex = $index;
                    $foundheader = new tdt\core\universalfilter\data\UniversalFilterTableHeader(array($columninfo), true, true);
                }
                
            }
            return $foundheader;//if null: identifier not found
        }else{
            //check single values for another match (to give an exception)
            for ($index = 0; $index < $topenv->getSingleValueCount(); $index++) {
                if($topenv->getSingleValueHeader($index)->matchName(explode(".", $fullid))){
                    throw new Exception("Ambiguos identifier: \"".$fullid."\". Please use aliases to remove the ambiguity.");//can only occured in nested querys or joins
                }
            }
            
            //return
            $newHeaderColumn=$originalheader->getColumnInformationById($columnid)->cloneColumnNewId();

            $columnHeader = new tdt\core\universalfilter\data\UniversalFilterTableHeader(array($newHeaderColumn), $originalheader->isSingleRowByConstruction(), true);

            return $columnHeader;
        }
    }
    
    /**
     * Get a column from the data (content)
     * @param UniversalFilterTableHeader $header
     * @return UniversalFilterTableContent
     */
    private function getColumnDataContent($table, $fullid, $header){//get a single column from the table
        $content=$table->getContent();
        
        if($fullid=="*"){
            //special case
            
            //have to copy because of ->tryDestroyTable on this one would otherwise also affect the full table...
            //TODO: while we are copying anyway, we could also change the id's!!! (only matters in one case: select *, * from ...)
            $contentCopy = new tdt\core\universalfilter\data\UniversalFilterTableContent();
            
            for ($rowindex = 0; $rowindex < $content->getRowCount(); $rowindex++) {
                $contentCopy->addRow($content->getRow($rowindex));
            }
            
            return $contentCopy;
        }
        
        $oldheader = $table->getHeader();
        $oldcolumnid = $oldheader->getColumnIdByName($fullid);
        
        $newcolumnid = $header->getColumnId();

        //copyFields
        //$oldcolumnid -> $newcolumnid
        
        $newContent= new tdt\core\universalfilter\data\UniversalFilterTableContent();
        $rows=array();
        for ($index = 0; $index < $content->getRowCount(); $index++) {
            $oldRow = $content->getRow($index);
            $newRow = new tdt\core\universalfilter\data\UniversalFilterTableContentRow();
            $oldRow->copyValueTo($newRow, $oldcolumnid, $newcolumnid);
            
            $newContent->addRow($newRow);
        }
        
        return $newContent;
    }
    
    public function filterSingleSourceUsages(tdt\core\universalfilter\data\UniversalFilterNode $parentNode, $parentIndex){
        if(!$this->isNewTable){
            return array();
        }else{
            $sourceId=$this->interpreter->getTableManager()->getSourceIdFromIdentifier($this->filter->getIdentifierString());
            return array(new tdt\core\universalfilter\interpreter\sourceusage\SourceUsageData($this->filter, $parentNode, $parentIndex, $sourceId));
        }
    }
}

?>
