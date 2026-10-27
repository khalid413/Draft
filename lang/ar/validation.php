<?php

// Only the rules visitor forms use; the admin area stays English.
return [
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام فقط.',
    'between' => ['numeric' => 'يجب أن تكون قيمة :attribute بين :min و:max.', 'string' => 'يجب أن يحتوي :attribute على ما بين :min و:max حرفًا.'],
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'in' => 'قيمة :attribute غير صالحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'max' => ['numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.', 'string' => 'يجب ألا يتجاوز :attribute :max حرفًا.', 'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.'],
    'min' => ['numeric' => 'يجب أن تكون قيمة :attribute :min على الأقل.', 'string' => 'يجب أن يحتوي :attribute على :min أحرف على الأقل.', 'array' => 'يجب أن يحتوي :attribute على :min عناصر على الأقل.'],
    'prohibited' => 'حقل :attribute غير مسموح به.',
    'required' => 'حقل :attribute مطلوب.',
    'size' => ['numeric' => 'يجب أن تكون قيمة :attribute :size.', 'string' => 'يجب أن يحتوي :attribute على :size أحرف.', 'array' => 'يجب أن يحتوي :attribute على :size عناصر.'],
    'string' => 'يجب أن يكون :attribute نصًا.',
    'uuid' => 'يجب أن يكون :attribute معرّفًا صالحًا.',
    'attributes' => ['name' => 'الاسم', 'code' => 'الرمز', 'rounds' => 'عدد الجولات', 'capacity' => 'عدد اللاعبين', 'players' => 'اللاعبون', 'locale' => 'اللغة'],
];
