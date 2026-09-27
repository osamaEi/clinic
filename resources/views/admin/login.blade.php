@extends('layouts.base')
@section('title', 'دخول الإدارة')

@section('body')
<main class="min-h-screen grid place-items-center p-6">
  <form method="POST" action="{{ route('admin.login') }}" class="w-full max-w-sm bg-white border border-line rounded-xl2 shadow-card p-6 space-y-4">
    @csrf
    <div>
      <h1 class="text-xl font-bold">لوحة إدارة المنصة</h1>
      <p class="text-muted text-sm mt-1">للمسؤولين عن الاشتراكات فقط.</p>
    </div>
    @error('email')<p class="text-sm text-rose bg-rose/5 rounded-lg p-2.5">{{ $message }}</p>@enderror
    <div><label class="lbl" for="email">البريد</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="field" dir="ltr"></div>
    <div><label class="lbl" for="password">كلمة السر</label>
      <input id="password" name="password" type="password" required class="field" dir="ltr"></div>
    <button class="w-full bg-primary text-white font-medium py-2.5 rounded-xl2 hover:bg-primaryd">دخول</button>
  </form>
</main>
@endsection
