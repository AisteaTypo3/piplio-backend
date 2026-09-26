<?php
return [
    'ctrl' => ['title' => 'Piplio Kinderprofil', 'label' => 'display_name', 'tstamp' => 'tstamp', 'crdate' => 'crdate', 'delete' => 'deleted', 'enablecolumns' => ['disabled' => 'hidden'], 'security' => ['ignorePageTypeRestriction' => true], 'typeicon_classes' => ['default' => 'status-user-frontend']],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'parent' => ['label' => 'Elternkonto', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'foreign_table' => 'tx_pipliobackend_parent', 'required' => true]],
        'display_name' => ['label' => 'Name', 'config' => ['type' => 'input', 'required' => true, 'eval' => 'trim']],
        'avatar' => ['label' => 'Avatar', 'config' => ['type' => 'input', 'eval' => 'trim']],
    ],
    'types' => ['0' => ['showitem' => 'hidden, parent, display_name, avatar']],
];
