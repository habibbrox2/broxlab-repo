@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ $title ?? 'Compare Phones' }}</h1>
    </div>

    @if(empty($phones))
        <div class="text-center py-12 text-slate-500 dark:text-slate-400">
            <i class="lucide lucide-git-compare w-8 h-8 mx-auto mb-2"></i>
            <p>Select phones to compare. Use the URL: <code class="text-xs">/mobiles/compare?ids=1,2,3</code></p>
            <p class="mt-2 text-sm">Browse <a href="/mobiles" class="text-indigo-600 dark:text-indigo-400 hover:underline">all phones</a> and add their IDs to the compare URL.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-slate-100 dark:bg-slate-800">
                        <th class="border border-slate-200 dark:border-slate-700 px-4 py-3 text-left font-semibold text-slate-900 dark:text-slate-100">Specification</th>
                        @foreach($phones as $phone)
                            <th class="border border-slate-200 dark:border-slate-700 px-4 py-3 text-center font-semibold text-slate-900 dark:text-slate-100">
                                {{ $phone['brand_name'] ?? '' }} {{ $phone['model_name'] ?? '' }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-white dark:bg-slate-900">
                        <td class="border border-slate-200 dark:border-slate-700 px-4 py-3 font-medium">Image</td>
                        @foreach($phones as $phone)
                            <td class="border border-slate-200 dark:border-slate-700 px-4 py-3 text-center">
                                @php
                                    $img = $phone['images'][0]['image_url'] ?? ($phone['image_path'] ?? null);
                                @endphp
                                @if($img)
                                    <img src="{{ $img }}" alt="{{ $phone['brand_name'] ?? '' }} {{ $phone['model_name'] ?? '' }}" class="max-w-[100px] max-h-[100px] mx-auto rounded-lg object-contain">
                                @else
                                    <span class="text-slate-400">No image</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @php
                        $specs = [];
                        foreach ($phones as $idx => $phone) {
                            foreach (($phone['specifications'] ?? []) as $spec) {
                                $specs[$spec['spec_key']]['phones'][$idx] = $spec['spec_value'];
                                $specs[$spec['spec_key']]['keys'][] = $spec['spec_key'];
                            }
                        }
                    @endphp
                    @foreach($specs as $key => $data)
                        <tr class="bg-slate-50 dark:bg-slate-800/50">
                            <td class="border border-slate-200 dark:border-slate-700 px-4 py-3 font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                            @foreach($phones as $idx => $phone)
                                <td class="border border-slate-200 dark:border-slate-700 px-4 py-3 text-center text-sm">
                                    {{ $data['phones'][$idx] ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
