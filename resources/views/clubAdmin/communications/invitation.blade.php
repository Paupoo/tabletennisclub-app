<x-app-layout>
    <div class="mx-auto max-w-md py-8">
        <x-card :title="__('For whom?')" :subtitle="__('Choose who you are registering. You will then land on their registrations.')" shadow separator>
            <div class="space-y-3">
                @foreach ($choices as $choice)
                    <form method="POST" action="{{ route('communications.invitation.choose', [$type, $id]) }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $choice->id }}">
                        <button type="submit" class="btn btn-block btn-outline justify-start">
                            <x-icon name="o-user" class="h-5 w-5" />
                            {{ $choice->full_name }}
                        </button>
                    </form>
                @endforeach
            </div>
        </x-card>
    </div>
</x-app-layout>
