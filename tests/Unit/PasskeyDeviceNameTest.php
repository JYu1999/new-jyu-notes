<?php

namespace Tests\Unit;

use App\Support\PasskeyDeviceName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * JYU-566: a new passkey is named after the device it was added on (「MacBook · Safari」), renameable later.
 */
class PasskeyDeviceNameTest extends TestCase
{
    public static function agents(): array
    {
        return [
            'Mac Safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', 'Mac · Safari'],
            'Mac Chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Mac · Chrome'],
            'Mac Firefox' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:133.0) Gecko/20100101 Firefox/133.0', 'Mac · Firefox'],
            'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'iPhone · Safari'],
            'iPhone Chrome' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0.6778.73 Mobile/15E148 Safari/604.1', 'iPhone · Chrome'],
            'iPad' => ['Mozilla/5.0 (iPad; CPU OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'iPad · Safari'],
            'Android Chrome' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'Android · Chrome'],
            'Windows Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'Windows · Edge'],
            'Linux Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0', 'Linux · Firefox'],
            'unknown' => ['curl/8.0', 'Passkey'],
            'empty' => ['', 'Passkey'],
        ];
    }

    #[DataProvider('agents')]
    public function test_names_the_device_and_browser(string $userAgent, string $expected): void
    {
        $this->assertSame($expected, PasskeyDeviceName::fromUserAgent($userAgent));
    }
}
