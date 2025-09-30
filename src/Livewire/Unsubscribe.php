<?php

namespace Dashed\DashedLaposta\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedLaposta\Classes\FormApis\NewsletterAPI;

class Unsubscribe extends Component
{
    public ?string $ezineCode = '';
    public ?string $tid = '';
    public array $blockData = [];

    public function mount(array $blockData = [])
    {
        $this->ezineCode = request()->get('ezinecode');
        $this->tid = request()->get('tid');
        $this->blockData = $blockData;

        if (! $this->ezineCode || ! $this->tid) {
            return redirect('/');
        }
    }

    public function submit()
    {
        NewsletterAPI::unsubscribe($this->ezineCode, $this->tid);

        Notification::make()
            ->body(Translation::get('unsubscribed-from-newsletter', 'laposta-newsletter', 'U bent uitgeschreven van de nieuwsbrief.'))
            ->success()
            ->send();

        $redirectUrl = linkHelper()->getUrl(Customsetting::get('laposta_redirect_after_unsubscribe_url'));
        if ($redirectUrl != '#') {
            return redirect($redirectUrl);
        }
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.laposta-newsletter.unsubscribe');
    }
}
