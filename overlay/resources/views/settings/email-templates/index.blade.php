<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Email Templates') }}
            </h2>
            @php
                $settingsRoute = Route::has('settings.index') ? route('settings.index') : url('/');
            @endphp
            <a href="{{ $settingsRoute }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300">
                {{ __('Back to Settings') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <p class="text-sm text-gray-600">
                            {{ __('Email templates control the text that EventSchedule sends to your attendees. Select a template below to review or customise it.') }}
                        </p>
                    </div>

                    @if (count($templates) === 0)
                        <div class="rounded-md bg-yellow-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.451 11.48C18.944 15.943 18.094 17 16.823 17H3.177c-1.27 0-2.121-1.057-1.371-2.421l6.451-11.48zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-.25-5.75a.75.75 0 00-1.5 0v3.5a.75.75 0 001.5 0v-3.5z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">{{ __('No email templates were found in the expected directories.') }}</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>{{ __('Add Blade templates under resources/views/emails, mail, email, or mailers to manage them here.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flow-root">
                            <ul role="list" class="-my-5 divide-y divide-gray-200">
                                @foreach ($templates as $template)
                                    <li class="py-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ $template['label'] }}</p>
                                                <p class="text-sm text-gray-500">{{ $template['relative'] }}</p>
                                            </div>
                                            <div class="ml-4">
                                                <a href="{{ route('settings.email-templates.edit', ['template' => $template['key']]) }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                    {{ __('Edit') }}
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
