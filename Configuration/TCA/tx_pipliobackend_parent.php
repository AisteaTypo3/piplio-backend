<?php
return [
    'ctrl' => ['title' => 'Piplio Elternkonto', 'label' => 'email', 'tstamp' => 'tstamp', 'crdate' => 'crdate', 'delete' => 'deleted', 'enablecolumns' => ['disabled' => 'hidden'], 'security' => ['ignorePageTypeRestriction' => true], 'typeicon_classes' => ['default' => 'status-user-group-frontend']],
    'columns' => [
        'hidden' => ['config' => ['type' => 'check', 'renderType' => 'checkboxToggle']],
        'email' => ['label' => 'E-Mail', 'config' => ['type' => 'email', 'required' => true, 'eval' => 'trim,unique']],
        'session_expires' => ['label' => 'Sitzung gültig bis', 'config' => ['type' => 'datetime', 'readOnly' => true]],
    ],
    'types' => ['0' => ['showitem' => 'hidden, email, session_expires']],
];
