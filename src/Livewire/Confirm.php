<?php

namespace Dashed\DashedLaposta\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedLaposta\Classes\FormApis\NewsletterAPI;

class Confirm extends Component
{
    public ?string $aapKey = '';
    public ?string $tid = '';
    public array $blockData = [];

    public function mount(array $blockData = [])
    {
        $this->aapKey = request()->get('aapkey');
        $this->tid = request()->get('tid');
        $this->blockData = $blockData;

        if (! $this->aapKey) {
            return redirect('/');
        }

        $this->submit();
    }

    public function submit()
    {
        NewsletterAPI::confirm($this->aapKey, $this->tid);

        Notification::make()
            ->body(Translation::get('confirmed-newsletter-subscription', 'laposta-newsletter', 'U bent aangemeld voor de nieuwsbrief.'))
            ->success()
            ->send();

        $redirectUrl = linkHelper()->getUrl(Customsetting::get('laposta_redirect_after_confirm_url'));
        if ($redirectUrl != '#') {
            return redirect($redirectUrl);
        }
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.laposta-newsletter.confirm');
    }
}
