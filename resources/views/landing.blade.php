@extends('layouts.base')
@section('title', 'عيادتي')

@section('body')
<header class="bg-deep text-white relative overflow-hidden">
  <div class="absolute -left-24 -top-24 w-96 h-96 rounded-full bg-primary/25 blur-3xl"></div>
  <div class="absolute -right-16 bottom-0 w-80 h-80 rounded-full bg-mint/15 blur-3xl"></div>
  <nav class="relative max-w-6xl mx-auto px-4 lg:px-8 h-20 flex items-center gap-3">
    <span class="w-10 h-10 rounded-xl2 bg-white/10 grid place-items-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M8 3v4M16 3v4M6 7h12a2 2 0 0 1 2 2v9a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V9a2 2 0 0 1 2-2Z"/><path d="M12 11v6M9 14h6"/></svg>
    </span>
    <span class="font-bold text-lg">عيادتي</span>
    <a href="/app/" class="mr-auto text-sm text-white/80 hover:text-white">تسجيل الدخول</a>
    <a href="/app/#register" class="bg-white text-deep text-sm font-medium px-4 py-2.5 rounded-xl2 hover:bg-white/90">جرّب مجاناً</a>
  </nav>
  <div class="relative max-w-6xl mx-auto px-4 lg:px-8 pt-10 pb-24 grid lg:grid-cols-2 gap-10 items-center">
    <div>
      <p class="text-mint text-sm font-medium">نظام سحابي — ويشتغل من غير نت</p>
      <h1 class="text-3xl lg:text-5xl font-bold leading-[1.25] mt-3">كل مواعيد العيادة وملفات المرضى في مكان واحد.</h1>
      <p class="text-white/70 mt-5 leading-relaxed">الاستقبال يسجل الوصول، التمريض ياخد القياسات، والدكتور يكشف ويطبع الروشتة في ضغطة. النت لو قطع الشغل مبيقفش — وأول ما يرجع كل حاجة بتتزامن لوحدها.</p>
      <div class="flex flex-wrap gap-3 mt-8">
        <a href="/app/#register" class="bg-white text-deep font-medium px-6 py-3 rounded-xl2 hover:bg-white/90">ابدأ تجربة {{ \App\Models\Clinic::TRIAL_DAYS }} يوم مجاناً</a>
        <a href="#pricing" class="border border-white/25 px-6 py-3 rounded-xl2 hover:bg-white/10">الأسعار</a>
      </div>
      <p class="text-white/45 text-xs mt-4">من غير كارت — ومن غير تسطيب.</p>
    </div>
    <div class="grid grid-cols-2 gap-4">
      @foreach ([
        ['يشتغل أوفلاين', 'البيانات محفوظة على الجهاز وبتترفع لوحدها أول ما النت يرجع.'],
        ['لوحة انتظار', 'محجوز ← وصل ← عند الدكتور ← خلص، بالسحب والإفلات.'],
        ['روشتة بالضغط', 'جرعات ومدد جاهزة، تكرار الروشتة، وتحذير الحساسية.'],
        ['فريق كامل', 'حسابات للدكتور والتمريض والاستقبال بصلاحيات مختلفة.'],
      ] as [$t, $d])
        <div class="bg-white/5 border border-white/10 rounded-xl2 p-5">
          <p class="font-semibold">{{ $t }}</p>
          <p class="text-white/60 text-sm mt-2 leading-relaxed">{{ $d }}</p>
        </div>
      @endforeach
    </div>
  </div>
</header>

<section id="pricing" class="max-w-6xl mx-auto px-4 lg:px-8 py-20">
  <h2 class="text-2xl lg:text-3xl font-bold text-center">باقات على قد العيادة</h2>
  <p class="text-muted text-center mt-2">كل الباقات فيها كل المميزات — الفرق في عدد المستخدمين والمساحة.</p>
  <div class="grid md:grid-cols-3 gap-5 mt-10">
    @foreach ($plans as $plan)
      <div class="bg-white border {{ $loop->index === 1 ? 'border-primary shadow-pop' : 'border-line shadow-card' }} rounded-xl2 p-6 flex flex-col">
        @if ($loop->index === 1)<span class="self-start text-xs bg-primary/10 text-primary px-2.5 py-1 rounded-full mb-3">الأكثر اختياراً</span>@endif
        <h3 class="font-semibold text-lg">{{ $plan->name }}</h3>
        <p class="mt-3"><span class="text-4xl font-bold num">{{ number_format($plan->price_monthly) }}</span> <span class="text-muted text-sm">جنيه / شهر</span></p>
        <ul class="mt-6 space-y-2.5 text-sm flex-1">
          <li>{{ $plan->max_users ? $plan->max_users.' مستخدمين' : 'مستخدمين بلا حدود' }}</li>
          <li>{{ $plan->max_patients ? number_format($plan->max_patients).' مريض' : 'مرضى بلا حدود' }}</li>
          <li>{{ $plan->max_storage_mb >= 1024 ? ($plan->max_storage_mb / 1024).' جيجا' : $plan->max_storage_mb.' ميجا' }} للأشعة والتحاليل</li>
          <li>شغل أوفلاين ومزامنة تلقائية</li>
        </ul>
        <a href="/app/?plan={{ $plan->slug }}#register" class="mt-6 text-center {{ $loop->index === 1 ? 'bg-primary text-white hover:bg-primaryd' : 'border border-line hover:border-primary hover:text-primary' }} font-medium px-4 py-3 rounded-xl2 transition">ابدأ التجربة</a>
      </div>
    @endforeach
  </div>
</section>

<footer class="border-t border-line py-8 text-center text-sm text-muted">© {{ date('Y') }} عيادتي</footer>
@endsection
