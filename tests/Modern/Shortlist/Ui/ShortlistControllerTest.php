<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Ui;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShortlistControllerTest extends WebTestCase
{
    public function test_browse_page_lists_available_offers(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Schowek ofert');
        self::assertSelectorTextContains('body', 'Toyota');
    }

    public function test_post_without_csrf_token_is_rejected(): void
    {
        $client = self::createClient();
        $client->request('POST', '/shortlist/add', ['offerId' => 'off-1001']);

        self::assertResponseStatusCodeSame(400);
    }

    public function test_post_without_offer_id_is_rejected(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/shortlist/add', ['_token' => $token]);

        self::assertResponseStatusCodeSame(400);
    }

    public function test_removed_offer_disappears_from_shortlist_section(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        $client->submitForm('Dodaj do schowka: Toyota Corolla');
        $client->followRedirect();

        $client->submitForm('Usuń Toyota Corolla ze schowka');
        self::assertResponseRedirects('/');
        $client->followRedirect();

        self::assertSelectorTextContains('[aria-label="Twój schowek"]', 'Schowek jest pusty');
    }

    public function test_exceeding_capacity_shows_flash_message(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        foreach (range(1001, 1011) as $number) {
            $client->request('POST', '/shortlist/add', [
                '_token' => $token,
                'offerId' => 'off-' . $number,
            ]);
            self::assertResponseRedirects('/');
        }

        $client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Schowek mieści najwyżej 10 ofert');
        self::assertSelectorTextContains('[aria-label="Twój schowek"]', '10/10');
        self::assertSelectorTextNotContains('[aria-label="Twój schowek"]', 'Seat Leon');
    }

    public function test_added_offer_appears_in_shortlist_section(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        $client->submitForm('Dodaj do schowka: Toyota Corolla');
        self::assertResponseRedirects('/');
        $client->followRedirect();

        self::assertSelectorTextContains('[aria-label="Twój schowek"]', 'Toyota Corolla');
    }
}
