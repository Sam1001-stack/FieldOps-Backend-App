<?php

return [
    'required' => ':attribute ist erforderlich.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'min' => [
        'string' => ':attribute muss mindestens :min Zeichen haben.',
    ],
    'in' => ':attribute ist ungültig.',
    'uuid' => ':attribute muss eine UUID sein.',
    'date' => ':attribute muss ein Datum sein.',
    'string' => ':attribute muss Text sein.',
    'integer' => ':attribute muss eine Zahl sein.',
    'boolean' => ':attribute muss wahr oder falsch sein.',
    'max' => [
        'string' => ':attribute darf höchstens :max Zeichen haben.',
    ],
    'attributes' => [
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'name' => 'Name',
        'role' => 'Rolle',
        'title' => 'Titel',
        'status' => 'Status',
        'plan' => 'Plan',
        'street' => 'Straße',
        'zip' => 'PLZ',
        'city' => 'Ort',
        'owner_name' => 'Inhaber-Name',
        'owner_email' => 'Inhaber-E-Mail',
        'owner_password' => 'Inhaber-Passwort',
        'organization_id' => 'Mandant',
        'monteur_id' => 'Monteur',
        'session_id' => 'Sitzung',
    ],
];
