{{--
    The app's one confirmation dialog, mounted once in the layout.

    A button asks it instead of the browser's confirm() box:

        x-on:click="$dispatch('app-confirm', { ...{{ Js::from([
            'message' => $question,
            'method' => 'delete',
            'params' => [$item->id],
            'danger' => true,
        ]) }}, id: $wire.$id })"

    `title` and `confirm` (the button label) are optional. On confirmation the
    dialog calls `method` with `params` on the Livewire component `id`, the
    same call wire:click would have made.
--}}
<div x-data="{
        title: '',
        message: '',
        confirmLabel: '',
        danger: false,
        request: null,
        ask(detail) {
            this.request = detail;
            this.title = detail.title ?? @js(__('Please confirm'));
            this.message = detail.message ?? '';
            this.confirmLabel = detail.confirm ?? @js(__('Confirm'));
            this.danger = detail.danger ?? false;
            this.$refs.dialog.showModal();
        },
        go() {
            const request = this.request;
            this.$refs.dialog.close();

            if (request?.id && request?.method) {
                Livewire.find(request.id)?.call(request.method, ...(request.params ?? []));
            }
        },
    }"
    x-on:app-confirm.window="ask($event.detail)">
    <dialog x-ref="dialog" class="modal" role="alertdialog" aria-modal="true"
        aria-labelledby="app-confirm-title" aria-describedby="app-confirm-message" data-confirm-dialog>
        <div class="modal-box">
            <h3 id="app-confirm-title" class="text-lg font-bold" x-text="title"></h3>
            <p id="app-confirm-message" class="mt-3 whitespace-pre-line text-sm" x-text="message"></p>
            <div class="modal-action">
                <button type="button" class="btn" x-on:click="$refs.dialog.close()">{{ __('Cancel') }}</button>
                <button type="button" class="btn" x-bind:class="danger ? 'btn-error' : 'btn-primary'"
                    x-on:click="go()" x-text="confirmLabel"></button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop backdrop-blur">
            <button type="submit">{{ __('Cancel') }}</button>
        </form>
    </dialog>
</div>
