<?php

// Only the rules visitor forms use; the admin area stays English.
return [
    'alpha_num' => 'Le champ :attribute ne peut contenir que des lettres et des chiffres.',
    'between' => ['numeric' => 'Le champ :attribute doit être entre :min et :max.', 'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.'],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'distinct' => 'Le champ :attribute a une valeur en double.',
    'in' => 'La valeur de :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => ['numeric' => 'Le champ :attribute ne peut pas dépasser :max.', 'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.', 'array' => 'Le champ :attribute ne peut pas avoir plus de :max éléments.'],
    'min' => ['numeric' => 'Le champ :attribute doit être au moins :min.', 'string' => 'Le champ :attribute doit contenir au moins :min caractères.', 'array' => 'Le champ :attribute doit avoir au moins :min éléments.'],
    'prohibited' => 'Le champ :attribute n’est pas autorisé.',
    'required' => 'Le champ :attribute est obligatoire.',
    'size' => ['numeric' => 'Le champ :attribute doit être :size.', 'string' => 'Le champ :attribute doit contenir :size caractères.', 'array' => 'Le champ :attribute doit contenir :size éléments.'],
    'string' => 'Le champ :attribute doit être un texte.',
    'uuid' => 'Le champ :attribute doit être un identifiant valide.',
    'attributes' => ['name' => 'nom', 'code' => 'code', 'rounds' => 'nombre de manches', 'capacity' => 'nombre de joueurs', 'players' => 'joueurs', 'locale' => 'langue'],
];
