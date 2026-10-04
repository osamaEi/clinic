<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Budget;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Treatment;
use ArrayObject;

/**
 * Demo patients, appointments, records, treatments, expenses and budgets for one clinic.
 *
 * Primary keys are global (client-generated), so with $fixedIds off each row gets
 * a fresh id in the client's Date.now() * 1000 + n scheme, and file numbers continue
 * after the clinic's highest one. Model events must stay on (Syncable stamps versions).
 */
class DemoDataSeeder
{
    public static function seed(Clinic $clinic, bool $fixedIds = false): void
    {
        [$id, $make, $ids] = self::writer($clinic, $fixedIds);
        $d = fn (int $days) => now()->addDays($days)->toDateString();
        $fileNo = (int) Patient::withTrashed()->where('clinic_id', $clinic->id)->max('file_no');

        $patients = [
            [1, 'سمر أحمد مصطفى', '0102 334 5566', 34, 'أنثى', 'A+', 'قولون عصبي', 'بنسلين', '115/75', '96 mg/dl', 64, 163, -30, '', -120],
            [2, 'محمد الخولي أحمد', '0111 887 2210', 52, 'ذكر', 'O+', 'ضغط مرتفع', null, '145/95', '104 mg/dl', 92, 176, -7, 'بيتأخر عن مواعيده — يتصل بيه قبلها بيوم', -90],
            [3, 'منة الله جنيدي', '0128 445 9080', 27, 'أنثى', 'B-', 'لا يوجد', null, null, null, null, null, null, '', -40],
            [4, 'سيد حسن أحمد', '0100 776 1122', 61, 'ذكر', 'AB+', 'سكري نوع ٢', 'سلفا', '138/88', '186 mg/dl', 81, 170, -14, '', -200],
            [5, 'ياسمين عبد الرحمن', '0106 233 4411', 19, 'أنثى', 'O-', 'أنيميا', null, null, null, null, null, null, '', -12],
            [6, 'كريم مدحت فؤاد', '0114 909 3321', 45, 'ذكر', 'A-', 'قولون عصبي', null, null, null, null, null, null, '', -8],
        ];
        foreach ($patients as [$pid, $name, $phone, $age, $gender, $blood, $chronic, $allergy, $bp, $sugar, $w, $h, $vd, $note, $created]) {
            $make(Patient::class, $id("p$pid", $pid), [
                'file_no' => ++$fileNo, 'name' => $name, 'phone' => $phone, 'age' => $age, 'gender' => $gender,
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
        foreach ($appts as [$aid, $pid, $day, $time, $type, $status, $fee, $paid, $method, $arrived]) {
            $make(Appointment::class, $id("a$aid", $aid), ['patient_id' => $ids["p$pid"], 'date' => $d($day), 'time' => $time,
                'type' => $type, 'status' => $status, 'fee' => $fee, 'paid' => $paid, 'method' => $method, 'arrived' => $arrived]);
        }

        $records = [
            [1, 1, -30, 'ألم متكرر أعلى البطن بعد الأكل', 'التهاب في جدار المعدة',
                "أوميبرازول ٢٠مج — قرص قبل الفطار بنصف ساعة لمدة ١٤ يوم\nدواجيست — بعد الأكل عند اللزوم", 'تحليل H. Pylori', 'بعد أسبوعين'],
            [2, 2, -7, 'دوخة وصداع في الصباح', 'ارتفاع ضغط غير منتظم مع الدواء الحالي',
                "كونكور ٥مج — نصف قرص صباحاً\nمتابعة قياس الضغط يومياً", 'وظائف كلى + صورة دم', 'بعد أسبوع'],
            [3, 4, -14, 'إرهاق عام وعطش متكرر', 'سكري غير منضبط', 'ميتفورمين ٥٠٠مج — قرص بعد الفطار والعشا', 'سكر تراكمي HbA1c', 'بعد شهر'],
        ];
        foreach ($records as [$rid, $pid, $day, $complaint, $diagnosis, $rx, $tests, $next]) {
            $make(MedicalRecord::class, $id("r$rid", $rid), ['patient_id' => $ids["p$pid"], 'date' => $d($day), 'complaint' => $complaint,
                'diagnosis' => $diagnosis, 'rx' => $rx, 'tests' => $tests, 'next_visit' => $next]);
        }

        $treatments = [
            [1, 1, 'أوميبرازول ٢٠مج', 'قرص قبل الفطار بنصف ساعة', '١٤ يوم', -30, 'يوقف لو حصل صداع مستمر', true],
            [2, 1, 'دواجيست', 'قرص بعد الأكل عند اللزوم', 'حسب الحاجة', -30, '', true],
            [3, 2, 'كونكور ٥مج', 'نصف قرص صباحاً', 'مستمر', -7, 'قياس الضغط يومياً وتسجيله', true],
            [4, 4, 'ميتفورمين ٥٠٠مج', 'قرص بعد الفطار والعشا', 'مستمر', -14, 'يتاخد مع الأكل لتجنب اضطراب المعدة', true],
            [5, 4, 'أنسولين مخلوط', '١٢ وحدة قبل العشا', 'شهر', -60, 'اتوقف بعد تحسن السكر التراكمي', false],
        ];
        foreach ($treatments as [$tid, $pid, $drug, $dose, $duration, $day, $note, $active]) {
            $make(Treatment::class, $id("t$tid", $tid), ['patient_id' => $ids["p$pid"], 'drug' => $drug, 'dose' => $dose,
                'duration' => $duration, 'start_date' => $d($day), 'note' => $note, 'active' => $active]);
        }

        self::seedBudget($clinic, $fixedIds);
    }

    /** Monthly spending limits plus this month's and last month's expenses. */
    public static function seedBudget(Clinic $clinic, bool $fixedIds = false): void
    {
        [$id, $make] = self::writer($clinic, $fixedIds);

        $budgets = [[1, 'إيجار', 6000], [2, 'مرتبات', 9000], [3, 'كهرباء ومياه', 900], [4, 'مستلزمات طبية', 2000], [5, 'دعاية وتسويق', 800]];
        foreach ($budgets as [$bid, $cat, $amount]) {
            $make(Budget::class, $id("b$bid", $bid), ['category' => $cat, 'amount' => $amount]);
        }

        // [id, months ago, day of that month, ...]
        $month = fn (int $monthsAgo, int $day) => now()->startOfMonth()->subMonthsNoOverflow($monthsAgo)->day($day)->toDateString();
        $expenses = [
            [1, 0, 1, 'إيجار', 6000, 'تحويل', 'إيجار الشهر'],
            [2, 0, 1, 'مرتبات', 4500, 'كاش', 'أ. هبة — استقبال'],
            [3, 0, 1, 'مرتبات', 4500, 'كاش', 'أ. منى — تمريض'],
            [4, 0, 2, 'مستلزمات طبية', 1350, 'كاش', 'جوانتيات وسرنجات وشاش'],
            [5, 0, 3, 'دعاية وتسويق', 1200, 'فيزا', 'إعلان فيسبوك'],
            [6, 1, 1, 'إيجار', 6000, 'تحويل', 'إيجار الشهر'],
            [7, 1, 1, 'مرتبات', 9000, 'كاش', 'مرتبات الفريق'],
            [8, 1, 10, 'كهرباء ومياه', 780, 'كاش', ''],
            [9, 1, 14, 'مستلزمات طبية', 2300, 'كاش', 'جهاز ضغط جديد + مستلزمات'],
            [10, 1, 20, 'صيانة', 450, 'كاش', 'صيانة التكييف'],
            [11, 1, 25, 'إنترنت وتليفون', 350, 'فيزا', ''],
        ];
        foreach ($expenses as [$eid, $ago, $day, $cat, $amount, $method, $note]) {
            $make(Expense::class, $id("e$eid", $eid), ['category' => $cat, 'amount' => $amount, 'date' => $month($ago, $day),
                'method' => $method, 'note' => $note]);
        }
    }

    /**
     * [$id, $make, $ids]: $id(key, fixed) hands out a row id and remembers it in $ids[key];
     * $make saves one row for the clinic.
     */
    private static function writer(Clinic $clinic, bool $fixedIds): array
    {
        $at = (int) (microtime(true) * 1000);
        $seq = 0;
        $ids = new ArrayObject;
        $id = function (string $key, int $fixed) use ($fixedIds, $at, &$seq, $ids) {
            return $ids[$key] = $fixedIds ? $fixed : $at * 1000 + $seq++;
        };
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

        return [$id, $make, $ids];
    }
}
