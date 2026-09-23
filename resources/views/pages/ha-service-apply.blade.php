@extends('layouts.app')

@section('title', $category->name . ' — Hero Alif')

@section('content')
<section class="mx-auto max-w-2xl px-4 py-12">
    <a href="/services-plus" class="text-sm text-emerald-700 hover:underline">← {{ t('All services') }}</a>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $category->name }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ $category->description }}</p>

    @if ($errors->any())
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="/services-plus/apply" enctype="multipart/form-data"
          class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <input type="hidden" name="category_id" value="{{ $category->id }}">

        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Your name') }} <span class="text-red-500">*</span></label>
            <input name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Mobile number') }} <span class="text-red-500">*</span></label>
            <input name="mobile" required value="{{ old('mobile') }}" placeholder="01XXXXXXXXX" pattern="01[3-9][0-9]{8}"
                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Email (optional)') }}</label>
            <input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Address (optional)') }}</label>
            <input name="address" value="{{ old('address') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>

        @foreach ($category->form_fields ?? [] as $field)
            @php $fname = 'field_' . ($field['name'] ?? ''); @endphp
            <div>
                <label class="text-sm font-semibold text-slate-700">{{ $field['label'] ?? $field['name'] }} @if(($field['required'] ?? false) === true)<span class="text-red-500">*</span>@endif</label>
                @if (($field['type'] ?? 'text') === 'textarea')
                    <textarea name="{{ $fname }}" rows="3" @required(($field['required'] ?? false) === true) class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">{{ old($fname) }}</textarea>
                @elseif (($field['type'] ?? '') === 'select')
                    <select name="{{ $fname }}" @required(($field['required'] ?? false) === true) class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                        @foreach (($field['options'] ?? []) as $opt)
                            <option value="{{ $opt }}" @selected(old($fname) === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                @else
                    <input name="{{ $fname }}" @required(($field['required'] ?? false) === true) value="{{ old($fname) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                @endif
            </div>
        @endforeach

        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Description') }}</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Preferred contact') }}</label>
            <select name="contact_method" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                <option value="phone">{{ t('Phone call') }}</option>
                <option value="whatsapp">{{ t('WhatsApp') }}</option>
                <option value="email">{{ t('Email') }}</option>
            </select>
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">{{ t('Documents (JPG, PNG, WEBP, PDF — max 5 MB each)') }}</label>
            <input type="file" name="documents[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"
                   class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold">
            <p class="mt-1 text-xs text-slate-400">{{ t('Sensitive documents (NID, passport) are stored privately and never publicly accessible.') }}</p>
        </div>

        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <span class="text-sm text-slate-600">{{ t('Service fee') }}: <strong>{{ (float) $category->base_fee > 0 ? '৳' . number_format((float) $category->base_fee, 2) : t('Pay after review') }}</strong></span>
            <button class="rounded-xl bg-emerald-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Submit request') }}</button>
        </div>
    </form>
</section>
@endsection
