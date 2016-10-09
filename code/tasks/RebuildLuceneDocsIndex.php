<?php

use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;

/**
 * Rebuilds the search indexes for the documentation pages. 
 *
 * For the hourly cron rebuild use RebuildLuceneDocusIndex_Hourly 
 *
 * @package docsviewer
 * @subpackage tasks
 */

class RebuildLuceneDocsIndex extends BuildTask
{
    protected $title = "Rebuild Documentation Search Indexes";
    
    protected $description = "
		Rebuilds the indexes used for the search engine in the docsviewer.";
    
    public function run($request)
    {
        $this->rebuildIndexes();
    }
    
    public function rebuildIndexes($quiet = false)
    {
        require_once 'Zend/Search/Lucene.php';

        ini_set("memory_limit", -1);
        ini_set('max_execution_time', 0);

        Filesystem::makeFolder(DocumentationSearch::get_index_location());
    
        // only rebuild the index if we have to. Check for either flush or the time write.lock.file
        // was last altered
        $lock = DocumentationSearch::get_index_location() .'/write.lock.file';
        $lockFileFresh = (file_exists($lock) && filemtime($lock) > (time() - (60 * 60 * 24)));

        echo "Building index in ". DocumentationSearch::get_index_location() . PHP_EOL;

        if ($lockFileFresh && !isset($_REQUEST['flush'])) {
            if (!$quiet) {
                echo "Index recently rebuilt. If you want to force reindex use ?flush=1";
            }
            
            return true;
        }

        try {
            $index = Zend_Search_Lucene::open(DocumentationSearch::get_index_location());
            $index->removeReference();
        } catch (Zend_Search_Lucene_Exception $e) {
            user_error($e);
        }

        try {
            $index = Zend_Search_Lucene::create(DocumentationSearch::get_index_location());
        } catch (Zend_Search_Lucene_Exception $c) {
            user_error($c);
        }

        // includes registration
        $manifest = new DocumentationManifest(true);
        $pages = $manifest->getPages();

        if ($pages) {
            $count = 0;
            
            // iconv complains about all the markdown formatting
            // turn off notices while we parse

            if (!Director::is_cli()) {
                echo "<ul>";
            }
            foreach ($pages as $url => $record) {
                $count++;
                $page = $manifest->getPage($url);

                $doc = new Zend_Search_Lucene_Document();
                $error = error_reporting();
                error_reporting(E_ALL ^ E_NOTICE);
                $content = $page->getHTML();
                error_reporting($error);

                $doc->addField(Zend_Search_Lucene_Field::Text('content', $content));
                $doc->addField($titleField = Zend_Search_Lucene_Field::Text('Title', $page->getTitle()));
                $doc->addField($breadcrumbField = Zend_Search_Lucene_Field::Text('BreadcrumbTitle', $page->getBreadcrumbTitle()));

                $doc->addField(Zend_Search_Lucene_Field::Keyword(
                    'Version', $page->getEntity()->getVersion()
                ));

                $doc->addField(Zend_Search_Lucene_Field::Keyword(
                    'Language', $page->getEntity()->getLanguage()
                ));

                $doc->addField(Zend_Search_Lucene_Field::Keyword(
                    'Entity', $page->getEntity()
                ));

                $doc->addField(Zend_Search_Lucene_Field::Keyword(
                    'Link', $page->Link()
                ));
    
                // custom boosts
                $titleField->boost = 3;
                $breadcrumbField->boost = 1.5;

                $boost = Config::inst()->get('DocumentationSearch', 'boost_by_path');

                foreach ($boost as $pathExpr => $boost) {
                    if (preg_match($pathExpr, $page->getRelativePath())) {
                        $doc->boost = $boost;
                    }
                }
                
                error_reporting(E_ALL ^ E_NOTICE);
                $index->addDocument($doc);

                if (!$quiet) {
                    if (Director::is_cli()) {
                        echo " * adding ". $page->getPath() ."\n";
                    } else {
                        echo "<li>adding ". $page->getPath() ."</li>\n";
                    }
                }
            }
        }

        $index->commit();
        
        if (!$quiet) {
            echo "complete.";
        }
    }
}
