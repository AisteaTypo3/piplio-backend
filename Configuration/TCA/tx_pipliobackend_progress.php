<?php
return [
    'ctrl' => ['title' => 'Piplio Lernstand', 'label' => 'profile', 'tstamp' => 'tstamp', 'crdate' => 'crdate', 'delete' => 'deleted', 'security' => ['ignorePageTypeRestriction' => true]],
    'columns' => [
        'profile' => ['label' => 'Kinderprofil', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'foreign_table' => 'tx_pipliobackend_childprofile', 'readOnly' => true]],
        'revision' => ['label' => 'Revision', 'config' => ['type' => 'number', 'readOnly' => true]],
        'progress_data' => ['label' => 'Lernstand (JSON)', 'config' => ['type' => 'text', 'readOnly' => true]],
    ],
    'types' => ['0' => ['showitem' => 'profile, revision, progress_data']],
];
