@extends('layouts.base')
@section('title', 'العيادات')

@php
  $badge = ['trial' => 'bg-primary/10 text-primary', 'active' => 'bg-mint/10 text-mint', 'expired' => 'bg-amber/10 text-amber', 'suspended' => 'bg-rose/10 text-rose'];
  $label = ['trial' => 'تجربة', 'active' => 'نشط', 'expired' => 'منتهي', 'suspended' => 'موقوف'];
@endphp

@section('body')
<header class="bg-deep text-white">
  <div class="max-w-7xl mx-auto px-4 lg:px-8 h-16 flex items-center gap-3">
    <span class="font-bold">إدارة المنصة</span>
    <span class="text-white/50 text-sm">العيادات والاشتراكات</span>
    <form method="POST" action="{{ route('admin.logout') }}" class="mr-auto">@csrf
      <button class="text-sm text-white/70 hover:text-white">خروج</button></form>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 lg:px-8 py-8 space-y-6">
  @if (session('status'))
    <p class="bg-mint/10 text-mint text-sm rounded-xl2 px-4 py-3">{{ session('status') }}</p>
  @endif

  <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
    @foreach ([['كل العيادات', $stats['total']], ['في التجربة', $stats['trial']], ['مشتركة', $stats['active']], ['منتهية/موقوفة', $stats['expired']], ['الإيراد الشهري (ج)', number_format($stats['mrr'])]] as [$t, $v])
      <div class="bg-white border border-line rounded-xl2 shadow-card p-5">
        <p class="text-3xl font-bold num">{{ $v }}</p><p class="text-sm text-muted mt-1">{{ $t }}</p>
      </div>
    @endforeach
  </div>

  <section class="bg-white border border-line rounded-xl2 shadow-card overflow-hidden">
    <form class="p-4 border-b border-line flex gap-3">
      <input name="q" value="{{ $q }}" placeholder="ابحث باسم العيادة أو بريد المستخدم" class="field max-w-sm">
      <button class="border border-line px-4 rounded-xl2 text-sm hover:border-primary hover:text-primary">بحث</button>
    </form>
    <div class="overflow-x-auto">
      <table class="w-full text-right text-sm" style="min-width:1000px">
        <thead class="bg-canvas text-muted text-xs"><tr>
          @foreach (['العيادة', 'صاحب الحساب', 'الباقة', 'الحالة', 'ينتهي', 'مستخدمين', 'مرضى', 'إجراءات'] as $h)
            <th class="px-4 py-3 font-medium">{{ $h }}</th>
          @endforeach
        </tr></thead>
        <tbody class="divide-y divide-line">
        @forelse ($clinics as $clinic)
          @php $state = $clinic->subscriptionState(); $owner = $clinic->users->first(); @endphp
          <tr>
            <td class="px-4 py-3"><p class="font-medium">{{ $clinic->name }}</p><p class="text-xs text-muted">{{ $clinic->specialty }}</p></td>
            <td class="px-4 py-3 text-xs"><span dir="ltr">{{ $owner?->email }}</span></td>
            <td class="px-4 py-3">
              <form method="POST" action="{{ route('admin.clinics.update', $clinic) }}">@csrf @method('PATCH')
                <input type="hidden" name="action" value="plan">
                <select name="plan_id" onchange="this.form.submit()" class="field !py-1.5 !w-32 text-xs">
                  @foreach ($plans as $p)<option value="{{ $p->id }}" @selected($p->id === $clinic->plan_id)>{{ $p->name }}</option>@endforeach
                </select>
              </form>
            </td>
            <td class="px-4 py-3"><span class="text-xs px-2.5 py-1 rounded-full {{ $badge[$state] }}">{{ $label[$state] }}</span></td>
            <td class="px-4 py-3 text-xs num">{{ $clinic->endsAt()?->format('Y-m-d') ?? '—' }}</td>
            <td class="px-4 py-3 num">{{ $clinic->users_count }}</td>
            <td class="px-4 py-3 num">{{ $clinic->patients_count }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-1.5 whitespace-nowrap">
                @foreach ([1 => 'شهر', 12 => 'سنة'] as $m => $t)
                  <form method="POST" action="{{ route('admin.clinics.update', $clinic) }}">@csrf @method('PATCH')
                    <input type="hidden" name="action" value="extend"><input type="hidden" name="months" value="{{ $m }}">
                    <button class="text-xs bg-primary text-white px-2.5 py-1.5 rounded-lg hover:bg-primaryd">+ {{ $t }}</button></form>
                @endforeach
                <form method="POST" action="{{ route('admin.clinics.update', $clinic) }}">@csrf @method('PATCH')
                  @if ($state === 'suspended')
                    <input type="hidden" name="action" value="resume"><button class="text-xs border border-line px-2.5 py-1.5 rounded-lg hover:border-mint hover:text-mint">تشغيل</button>
                  @else
                    <input type="hidden" name="action" value="suspend"><button class="text-xs border border-line px-2.5 py-1.5 rounded-lg hover:border-rose hover:text-rose" onclick="return confirm('توقيف العيادة؟ هتبقى قراءة فقط.')">إيقاف</button>
                  @endif
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="p-10 text-center text-muted">مفيش عيادات.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4">{{ $clinics->links() }}</div>
  </section>
</main>
@endsection
