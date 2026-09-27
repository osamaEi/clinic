<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'حقل :attribute لازم يكون بريد إلكتروني صحيح.',
    'unique' => ':attribute ده مستخدم قبل كده.',
    'exists' => ':attribute مش موجود.',
    'min' => [
        'string' => 'حقل :attribute لازم يكون :min حروف على الأقل.',
        'numeric' => 'حقل :attribute لازم يكون :min على الأقل.',
        'array' => 'حقل :attribute لازم يكون فيه :min عناصر على الأقل.',
        'file' => 'حجم :attribute لازم يكون :min كيلوبايت على الأقل.',
    ],
    'max' => [
        'string' => 'حقل :attribute مينفعش يزيد عن :max حرف.',
        'numeric' => 'حقل :attribute مينفعش يزيد عن :max.',
        'array' => 'حقل :attribute مينفعش يزيد عن :max عنصر.',
        'file' => 'حجم :attribute مينفعش يزيد عن :max كيلوبايت.',
    ],
    'string' => 'حقل :attribute لازم يكون نص.',
    'integer' => 'حقل :attribute لازم يكون رقم صحيح.',
    'numeric' => 'حقل :attribute لازم يكون رقم.',
    'boolean' => 'حقل :attribute لازم يكون صح أو غلط.',
    'date' => 'حقل :attribute لازم يكون تاريخ صحيح.',
    'array' => 'حقل :attribute لازم يكون قائمة.',
    'in' => 'قيمة :attribute مش مقبولة.',
    'regex' => 'صيغة :attribute مش صحيحة.',
    'file' => 'حقل :attribute لازم يكون ملف.',
    'mimes' => 'نوع :attribute لازم يكون: :values.',

    'attributes' => [
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة السر',
        'name' => 'الاسم',
        'clinic_name' => 'اسم العيادة',
        'phone' => 'التليفون',
        'role' => 'الدور',
        'plan' => 'الباقة',
        'file' => 'الملف',
    ],
];
