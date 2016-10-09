<?php

use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Form;

class DocumentationSearchForm extends Form
{
    public function __construct($controller)
    {
        $fields = new FieldList(
            TextField::create('q', _t('DocumentationViewer.SEARCH', 'Search'), '')
                ->setAttribute('placeholder', _t('DocumentationViewer.SEARCH', 'Search'))
        );

        $page = $controller->getPage();

        if ($page) {
            $versions = HiddenField::create(
                'Versions',
                _t('DocumentationViewer.VERSIONS', 'Versions'),
                $page->getEntity()->getVersion()
            );

            $fields->push($versions);
        }

        $actions = new FieldList(
            new FormAction('results', _t('DocumentationViewer.SEARCH', 'Search'))
        );

        parent::__construct($controller, 'DocumentationSearchForm', $fields, $actions);

        $this->disableSecurityToken();
        $this->setFormMethod('GET');
        $this->setFormAction($controller->Link('results'));

        $this->addExtraClass('search');
    }
}
