<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Email Templates') }}
            </h2>
            @php
                $settingsRoute = Route::has('settings.index') ? route('settings.index') : url('/');
            @endphp
            <a href="{{ $settingsRoute }}" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Back to Settings') }}
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <p class="text-sm text-gray-600">
                        {{ __('Review the available email templates and click edit to update the Blade markup used when EventSchedule sends each message.') }}
                    </p>

                    <div class="mt-6 border-t border-gray-200"></div>

                    <div class="mt-6">
                        <ul role="list" class="divide-y divide-gray-200">
                            @forelse ($templates as $template)
                                <li class="py-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $template['label'] }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{ $template['relative'] }}
                                            </p>
                                        </div>
                                        <div class="flex space-x-3">
                                            <a href="{{ route('settings.email-templates.edit', ['template' => $template['key']]) }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                {{ __('Edit') }}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="py-4">
                                    <p class="text-sm text-gray-500">
                                        {{ __('No email templates were found in the expected directories.') }}
                                    </p>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
