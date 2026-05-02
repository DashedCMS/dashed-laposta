<?php

/**
 * Lightweight stubs that stand in for the dashed-core / dashed-popups classes
 * that the Laposta PopupAPI references. The real classes live in other packages
 * (dashed/dashed-core, dashed/dashed-popups) which are NOT dev-dependencies of
 * dashed-laposta. The stubs only expose what the production code under test
 * touches: Customsetting::get(string) and a small bag of PopupView properties.
 */

namespace Dashed\DashedCore\Models {
    if (! class_exists(Customsetting::class, false)) {
        class Customsetting
        {
            public static array $values = [];

            public static function get(string $key, $siteId = null, $default = null)
            {
                return self::$values[$key] ?? $default;
            }

            public static function set(string $key, $value, $siteId = null): void
            {
                self::$values[$key] = $value;
            }

            public static function reset(): void
            {
                self::$values = [];
            }
        }
    }
}

namespace Dashed\DashedPopups\Models {
    if (! class_exists(PopupView::class, false)) {
        class PopupView
        {
            public ?int $id = null;

            public ?string $email = null;

            public ?string $ip_address = null;

            public ?string $url = null;

            public ?string $referrer = null;

            public ?string $device_type = null;

            public ?string $locale = null;

            public function __construct(array $attributes = [])
            {
                foreach ($attributes as $key => $value) {
                    $this->{$key} = $value;
                }
            }
        }
    }
}
