<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Model events must stay on: the Syncable trait stamps each row's sync version on save.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        User::updateOrCreate(['email' => 'admin@clinic.test'], [
            'name' => 'مدير المنصة',
            'password' => 'password',
            'is_super_admin' => true,
            'role' => 'doctor',
        ]);

        if (Clinic::where('name', 'عيادة الوفاء')->exists()) {
            return;
        }

        $clinic = Clinic::create([
            'name' => 'عيادة الوفاء',
            'specialty' => 'باطنة وجهاز هضمي',
            'doctor_name' => 'د. أحمد سمير',
            'phone' => '0100 123 4567',
            'address' => '٢٧ شارع جامعة الدول العربية، المهندسين',
            'fees' => Clinic::DEFAULT_FEES,
            'plan_id' => Plan::where('slug', 'pro')->value('id'),
            'status' => 'active',
            'subscription_ends_at' => now()->addYear(),
        ]);

        foreach ([
            ['د. أحمد سمير', 'doctor@demo.test', 'doctor', true],
            ['أ. منى خالد', 'nurse@demo.test', 'nurse', false],
            ['أ. هبة سعيد', 'reception@demo.test', 'secretary', false],
        ] as [$name, $email, $role, $owner]) {
            User::create(['clinic_id' => $clinic->id, 'name' => $name, 'email' => $email,
                'password' => 'password', 'role' => $role, 'is_owner' => $owner]);
        }

        $this->seedDemoData($clinic);
    }

    private function seedDemoData(Clinic $clinic): void
    {
        $d = fn (int $days) => now()->addDays($days)->toDateString();
        $at = (int) (microtime(true) * 1000);
        $make = function (string $class, int $id, array $attrs) use ($clinic, $at) {
            $m = new $class;
            $m->id = $id;
            $m->clinic_id = $clinic->id;
            $m->client_updated_at = $at;
            foreach ($attrs as $k => $v) {
                $m->{$k} = $v;
            }
            $m->save();
        };

        $patients = [
            [1, 'سمر أحمد مصطفى', '0102 334 5566', 34, 'أنثى', 'A+', 'قولون عصبي', 'بنسلين', '115/75', '96 mg/dl', 64, 163, -30, '', -120],
            [2, 'محمد الخولي أحمد', '0111 887 2210', 52, 'ذكر', 'O+', 'ضغط مرتفع', null, '145/95', '104 mg/dl', 92, 176, -7, 'بيتأخر عن مواعيده — يتصل بيه قبلها بيوم', -90],
            [3, 'منة الله جنيدي', '0128 445 9080', 27, 'أنثى', 'B-', 'لا يوجد', null, null, null, null, null, null, '', -40],
            [4, 'سيد حسن أحمد', '0100 776 1122', 61, 'ذكر', 'AB+', 'سكري نوع ٢', 'سلفا', '138/88', '186 mg/dl', 81, 170, -14, '', -200],
            [5, 'ياسمين عبد الرحمن', '0106 233 4411', 19, 'أنثى', 'O-', 'أنيميا', null, null, null, null, null, null, '', -12],
            [6, 'كريم مدحت فؤاد', '0114 909 3321', 45, 'ذكر', 'A-', 'قولون عصبي', null, null, null, null, null, null, '', -8],
        ];
        foreach ($patients as $i => [$id, $name, $phone, $age, $gender, $blood, $chronic, $allergy, $bp, $sugar, $w, $h, $vd, $note, $created]) {
            $make(Patient::class, $id, [
                'file_no' => $i + 1, 'name' => $name, 'phone' => $phone, 'age' => $age, 'gender' => $gender,
                'blood' => $blood, 'chronic' => $chronic, 'allergy' => $allergy, 'bp' => $bp, 'sugar' => $sugar,
                'weight' => $w, 'height' => $h, 'vitals_date' => $vd === null ? null : $d($vd), 'note' => $note,
                'registered_on' => $d($created),
            ]);
        }

        $appts = [
            [101, 1, 0, '10:00', 'كشف', 'تم الكشف', 300, true, 'كاش', '09:55'],
            [102, 2, 0, '11:30', 'متابعة', 'في الانتظار', 150, true, 'فيزا', '11:20'],
            [103, 3, 0, '12:15', 'كشف', 'محجوز', 300, false, null, null],
            [104, 4, 0, '16:00', 'استشارة', 'محجوز', 200, false, null, null],
            [105, 5, 0, '17:30', 'متابعة', 'محجوز', 150, false, null, null],
            [106, 6, 1, '11:00', 'كشف', 'محجوز', 300, false, null, null],
            [107, 2, -7, '12:00', 'كشف', 'تم الكشف', 300, true, 'كاش', null],
            [108, 4, -14, '13:00', 'كشف', 'تم الكشف', 300, true, 'كاش', null],
            [109, 1, -30, '18:00', 'كشف', 'تم الكشف', 300, true, 'فيزا', null],
        ];
        foreach ($appts as [$id, $pid, $day, $time, $type, $status, $fee, $paid, $method, $arrived]) {
            $make(Appointment::class, $id, ['patient_id' => $pid, 'date' => $d($day), 'time' => $time, 'type' => $type,
                'status' => $status, 'fee' => $fee, 'paid' => $paid, 'method' => $method, 'arrived' => $arrived]);
        }

        $records = [
            [1, 1, -30, 'ألم متكرر أعلى البطن بعد الأكل', 'التهاب في جدار المعدة',
                "أوميبرازول ٢٠مج — قرص قبل الفطار بنصف ساعة لمدة ١٤ يوم\nدواجيست — بعد الأكل عند اللزوم", 'تحليل H. Pylori', 'بعد أسبوعين'],
            [2, 2, -7, 'دوخة وصداع في الصباح', 'ارتفاع ضغط غير منتظم مع الدواء الحالي',
                "كونكور ٥مج — نصف قرص صباحاً\nمتابعة قياس الضغط يومياً", 'وظائف كلى + صورة دم', 'بعد أسبوع'],
            [3, 4, -14, 'إرهاق عام وعطش متكرر', 'سكري غير منضبط', 'ميتفورمين ٥٠٠مج — قرص بعد الفطار والعشا', 'سكر تراكمي HbA1c', 'بعد شهر'],
        ];
        foreach ($records as [$id, $pid, $day, $complaint, $diagnosis, $rx, $tests, $next]) {
            $make(MedicalRecord::class, $id, ['patient_id' => $pid, 'date' => $d($day), 'complaint' => $complaint,
                'diagnosis' => $diagnosis, 'rx' => $rx, 'tests' => $tests, 'next_visit' => $next]);
        }

        $treatments = [
            [1, 1, 'أوميبرازول ٢٠مج', 'قرص قبل الفطار بنصف ساعة', '١٤ يوم', -30, 'يوقف لو حصل صداع مستمر', true],
            [2, 1, 'دواجيست', 'قرص بعد الأكل عند اللزوم', 'حسب الحاجة', -30, '', true],
            [3, 2, 'كونكور ٥مج', 'نصف قرص صباحاً', 'مستمر', -7, 'قياس الضغط يومياً وتسجيله', true],
            [4, 4, 'ميتفورمين ٥٠٠مج', 'قرص بعد الفطار والعشا', 'مستمر', -14, 'يتاخد مع الأكل لتجنب اضطراب المعدة', true],
            [5, 4, 'أنسولين مخلوط', '١٢ وحدة قبل العشا', 'شهر', -60, 'اتوقف بعد تحسن السكر التراكمي', false],
        ];
        foreach ($treatments as [$id, $pid, $drug, $dose, $duration, $day, $note, $active]) {
            $make(Treatment::class, $id, ['patient_id' => $pid, 'drug' => $drug, 'dose' => $dose, 'duration' => $duration,
                'start_date' => $d($day), 'note' => $note, 'active' => $active]);
        }
    }
}
