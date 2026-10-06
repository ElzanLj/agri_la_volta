<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\BusyException;
use App\Domain\ConflictException;
use App\Domain\GuestCounts;
use App\Domain\PriceQuote;
use App\Domain\StateException;
use App\Domain\StayDates;
use App\Domain\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repository\ApartmentRepository;
use App\Repository\AvailabilityRepository;
use App\Security\OriginCheck;
use App\Security\RateLimiter;
use App\Site\FormToken;
use App\Site\Locale;
use App\Site\Routes;
use App\Site\Text;

/**
 * The public availability request, in separate steps (no JavaScript needed):
 * 1 dates and guests -> 2 available apartments with price -> 3 customer data -> 4 summary,
 * privacy consent and send -> "request received".
 *
 * Steps 1-3 only read. Nothing is stored before the final POST, and the price shown is always
 * calculated by the server (BookingService::previewRequest, the same code that stores the request):
 * no amount sent by the browser is ever read. The state reached is always "pending", never "confirmed".
 */
final class RequestFlowController extends SitePage
{
    private const TOKEN_PURPOSE = 'public-request';
    private const RATE_BUCKET = 'public_request';
    private const RATE_MAX = 6;
    private const RATE_WINDOW_SECONDS = 3600;
    private const HONEYPOT = 'contact_website';

    private const SEARCH_FIELDS = ['check_in', 'check_out', 'adults', 'children', 'pets'];
    private const CUSTOMER_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'notes'];

    // === Step 1: dates and guests ===========================================

    public function search(Request $request, string $locale): Response
    {
        $values = $this->values($request, false);
        if ($values['adults'] === '') {
            $values['adults'] = '2'; // form default only; the server validates whatever is sent
        }
        return $this->searchPage($locale, $values, []);
    }

    // === Step 2: available apartments =======================================

    public function apartments(Request $request, string $locale): Response
    {
        $values = $this->values($request, false);
        if ($values['check_in'] === '' && $values['check_out'] === '') {
            return $this->redirect('request', $locale);
        }
        ['stay' => $stay, 'guests' => $guests, 'errors' => $errors] = $this->checkSearch($values);
        if ($stay === null || $guests === null || $errors !== []) {
            return $this->searchPage($locale, $values, $errors, 422);
        }

        $apartmentRepo = new ApartmentRepository($this->app->db());
        $quoter = $this->app->services()->priceQuoter();
        $options = [];
        foreach ((new AvailabilityRepository($this->app->db()))->availableApartments($stay, $guests) as $row) {
            $apartment = $apartmentRepo->find((int) $row['id']);
            if ($apartment === null) {
                continue;
            }
            $quote = $quoter->quote((int) $row['id'], $stay, $guests);
            $options[] = [
                'slug' => (string) $apartment['slug'],
                'name' => (string) $apartment['name'],
                'max_guests' => $apartment['max_guests'] === null ? null : (int) $apartment['max_guests'],
                'summary' => $quote->summary($locale),
                'problem' => $this->problemText($apartment, $guests, $quote, $locale),
            ];
        }

        return $this->render('request/apartments', $locale, 'request.apartments', [
            'title' => Text::get('flow.apartments.title', $locale),
            'noindex' => true,
            'private' => true,
            'values' => $values,
            'stay' => $stay,
            'guests' => $guests,
            'options' => $options,
        ]);
    }

    // === Step 3: customer data ===============================================

    public function details(Request $request, string $locale): Response
    {
        $values = $this->values($request, false);
        $apartment = $this->bookableApartment($values, $locale);
        if ($apartment === null) {
            return $this->redirect('request.apartments', $locale, $this->searchQuery($values));
        }
        return $this->detailsPage($locale, $values, $apartment, []);
    }

    /** "Edit my details" from the summary: shows the form again with what was typed (writes nothing). */
    public function edit(Request $request, string $locale): Response
    {
        if (!OriginCheck::allowed($request, $this->app)) {
            return $this->forbidden($locale);
        }
        $values = $this->values($request, true);
        $apartment = $this->bookableApartment($values, $locale);
        if ($apartment === null) {
            return $this->redirect('request.apartments', $locale, $this->searchQuery($values));
        }
        return $this->detailsPage($locale, $values, $apartment, []);
    }

    // === Step 4: summary and consent =========================================

    public function summary(Request $request, string $locale): Response
    {
        if ($blocked = $this->guardPost($request, $locale, $this->app->config->int('PUBLIC_FORM_MIN_SECONDS', 3), 'details')) {
            return $blocked;
        }
        $values = $this->values($request, true);

        $apartment = $this->bookableApartment($values, $locale);
        if ($apartment === null) {
            return $this->redirect('request.apartments', $locale, $this->searchQuery($values));
        }
        return $this->summaryPage($locale, $values, $apartment, [], 200);
    }

    // === Send ================================================================

    public function submit(Request $request, string $locale): Response
    {
        if ($blocked = $this->guardPost($request, $locale, 0, 'summary')) {
            return $blocked;
        }
        $values = $this->values($request, true);

        $limiter = new RateLimiter($this->app->db());
        $client = $request->ip();
        if ($limiter->tooManyAttempts(self::RATE_BUCKET, $client, self::RATE_MAX, self::RATE_WINDOW_SECONDS)) {
            $this->app->logger->warning('Public request rate limited');
            return $this->problemPage($locale, 'flow.error.too_many', 429);
        }
        $limiter->hit(self::RATE_BUCKET, $client);

        $apartment = $this->bookableApartment($values, $locale);
        if ($apartment === null) {
            return $this->redirect('request.apartments', $locale, $this->searchQuery($values));
        }

        try {
            $result = $this->app->services()->bookingService()->createRequest($this->serviceInput($values, $apartment, $locale, $request->input('privacy_accepted') === '1'));
        } catch (ValidationException $e) {
            $errors = $this->translate($e->errors(), $locale, $e->context());
            if (array_keys($errors) === ['privacy_accepted']) {
                return $this->summaryPage($locale, $values, $apartment, $errors, 422);
            }
            return $this->detailsPage($locale, $values, $apartment, $errors, 422);
        } catch (ConflictException) {
            return $this->problemPage($locale, 'flow.error.conflict', 409);
        } catch (BusyException) {
            return $this->problemPage($locale, 'flow.error.busy', 503);
        } catch (StateException) {
            return $this->problemPage($locale, 'flow.error.unavailable', 422);
        }

        return $this->redirect('request.received', $locale, ['rif' => $result['reference']]);
    }

    public function received(Request $request, string $locale): Response
    {
        $reference = $request->query('rif');
        return $this->render('request/received', $locale, 'request.received', [
            'title' => Text::get('flow.received.title', $locale),
            'noindex' => true,
            'private' => true,
            'reference' => preg_match('/^[A-Z0-9][A-Z0-9-]{3,19}\z/', $reference) ? $reference : null,
        ]);
    }

    // === Pages ================================================================

    /** @param array<string, string> $values @param array<string, string> $errors messages, by field */
    private function searchPage(string $locale, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('request/search', $locale, 'request', [
            'title' => Text::get('request.title', $locale),
            'description' => Text::get('request.description', $locale),
            'noindex' => true,
            'private' => true,
            'values' => $values,
            'errors' => $this->translate($errors, $locale),
            'today' => $this->today(),
        ], [], $status);
    }

    /** @param array<string, string> $values @param array<string, mixed> $apartment @param array<string, string> $errors messages */
    private function detailsPage(string $locale, array $values, array $apartment, array $errors, int $status = 200): Response
    {
        return $this->render('request/details', $locale, 'request.details', [
            'title' => Text::get('flow.details.title', $locale),
            'noindex' => true,
            'private' => true,
            'values' => $values,
            'errors' => $errors,
            'apartment' => $apartment,
            'token' => FormToken::issue(self::TOKEN_PURPOSE),
            'honeypot' => self::HONEYPOT,
        ], [], $status);
    }

    /** @param array<string, string> $values @param array<string, mixed> $apartment @param array<string, string> $errors messages */
    private function summaryPage(string $locale, array $values, array $apartment, array $errors, int $status): Response
    {
        try {
            // The consent is asked on this page, so it is not checked yet when previewing.
            $preview = $this->app->services()->bookingService()->previewRequest($this->serviceInput($values, $apartment, $locale, true));
        } catch (ValidationException $e) {
            return $this->detailsPage($locale, $values, $apartment, $this->translate($e->errors(), $locale, $e->context()), 422);
        } catch (ConflictException) {
            return $this->problemPage($locale, 'flow.error.conflict', 409);
        } catch (StateException) {
            return $this->problemPage($locale, 'flow.error.unavailable', 422);
        }

        return $this->render('request/summary', $locale, 'request.summary', [
            'title' => Text::get('flow.summary.title', $locale),
            'noindex' => true,
            'private' => true,
            'values' => $values,
            'errors' => $errors,
            'apartment' => $apartment,
            'stay' => $preview['stay'],
            'guests' => $preview['guests'],
            'quote' => $preview['quote']->summary($locale),
            'token' => FormToken::issue(self::TOKEN_PURPOSE),
            'honeypot' => self::HONEYPOT,
        ], [], $status);
    }

    private function problemPage(string $locale, string $messageKey, int $status): Response
    {
        return $this->render('request/problem', $locale, 'request', [
            'title' => Text::get('flow.error.title', $locale),
            'noindex' => true,
            'private' => true,
            'messageKey' => $messageKey,
        ], [], $status);
    }

    // === Checks ===============================================================

    /**
     * Checks shared by the two POST steps: token, Origin, honeypot. Returns the response to send
     * when the request must not go on, or null when it may continue.
     */
    private function guardPost(Request $request, string $locale, int $minAgeSeconds, string $returnTo): ?Response
    {
        if (!OriginCheck::allowed($request, $this->app)) {
            $this->app->logger->warning('Public form refused: foreign origin', ['path' => $request->path]);
            return $this->forbidden($locale);
        }
        $state = FormToken::check($request->input('_form'), self::TOKEN_PURPOSE, $minAgeSeconds);
        if ($state === 'invalid') {
            $this->app->logger->warning('Public form refused: invalid token', ['path' => $request->path]);
            return $this->forbidden($locale);
        }
        if ($request->input(self::HONEYPOT) !== '') {
            // A bot filled the hidden field: pretend everything went well and store nothing.
            $this->app->logger->info('Public form dropped: honeypot');
            return $this->redirect('request.received', $locale);
        }
        if ($state === 'expired' || $state === 'too_fast') {
            $values = $this->values($request, true);
            $message = Text::get($state === 'expired' ? 'flow.error.expired' : 'flow.error.too_fast', $locale);
            $apartment = $this->bookableApartment($values, $locale);
            if ($apartment === null || $returnTo !== 'details') {
                return $this->searchPage($locale, $values, ['form' => $message], 422);
            }
            return $this->detailsPage($locale, $values, $apartment, ['form' => $message], 422);
        }
        return null;
    }

    private function forbidden(string $locale): Response
    {
        Locale::set($locale);
        return View::error(403);
    }

    /**
     * @param array<string, string> $values
     * @return array{stay: ?StayDates, guests: ?GuestCounts, errors: array<string, string>} error CODES by field
     */
    private function checkSearch(array $values): array
    {
        $errors = [];
        $stay = $guests = null;
        try {
            $candidate = StayDates::fromStrings($values['check_in'], $values['check_out']);
            $candidate->assertBookableFromPublic($this->today());
            $stay = $candidate;
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        try {
            $guests = GuestCounts::from($values['adults'], $values['children'] === '' ? 0 : $values['children'], $values['pets'] === '' ? 0 : $values['pets']);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        return ['stay' => $stay, 'guests' => $guests, 'errors' => $errors];
    }

    /**
     * The apartment chosen in step 2, only when it is still a valid choice for these dates and guests.
     *
     * @param array<string, string> $values
     * @return array<string, mixed>|null
     */
    private function bookableApartment(array $values, string $locale): ?array
    {
        ['stay' => $stay, 'guests' => $guests, 'errors' => $errors] = $this->checkSearch($values);
        if ($stay === null || $guests === null || $errors !== [] || !preg_match('/^[a-z0-9-]{1,100}\z/', $values['apartment'])) {
            return null;
        }
        $apartment = (new ApartmentRepository($this->app->db()))->findPublicBySlug($values['apartment'], $locale);
        if ($apartment === null || !(int) $apartment['accepts_online_requests']) {
            return null;
        }
        if ((new AvailabilityRepository($this->app->db()))->conflicts((int) $apartment['id'], $stay) !== []) {
            return null;
        }
        $quote = $this->app->services()->priceQuoter()->quote((int) $apartment['id'], $stay, $guests);
        if ($this->problemText($apartment, $guests, $quote, $locale) !== null) {
            return null;
        }
        return $apartment;
    }

    /** Why this apartment cannot take this request (guest limits, minimum stay), or null when it can. */
    private function problemText(array $apartment, GuestCounts $guests, PriceQuote $quote, string $locale): ?string
    {
        if ($guests->exceedsCapacity($apartment['max_guests'] === null ? null : (int) $apartment['max_guests'])) {
            return Text::get('err.over_capacity', $locale);
        }
        if ($apartment['max_children'] !== null && $guests->children > (int) $apartment['max_children']) {
            return Text::get('err.too_many_children', $locale, ['max' => (string) (int) $apartment['max_children']]);
        }
        if ($apartment['max_pets'] !== null && $guests->pets > (int) $apartment['max_pets']) {
            $max = (int) $apartment['max_pets'];
            return $max === 0 ? Text::get('err.pets_not_allowed', $locale) : Text::get('err.too_many_pets', $locale, ['max' => (string) $max]);
        }
        foreach ($quote->blockingIssues() as $issue) {
            if ($issue['code'] === 'below_min_nights') {
                return Text::get('err.below_minimum_stay', $locale, ['min' => (string) (int) $issue['min_nights']]);
            }
        }
        return null;
    }

    // === Data =================================================================

    /** Whitelisted form fields as plain strings (arrays and anything else become ""). @return array<string, string> */
    private function values(Request $request, bool $post): array
    {
        $values = [];
        foreach (array_merge(self::SEARCH_FIELDS, ['apartment'], self::CUSTOMER_FIELDS) as $field) {
            $values[$field] = trim($post ? $request->input($field) : $request->query($field));
        }
        // Notes keep their line breaks; trim() only removed the outer whitespace.
        return $values;
    }

    /** @param array<string, string> $values @return array<string, string> */
    private function searchQuery(array $values): array
    {
        return array_filter(array_intersect_key($values, array_flip(self::SEARCH_FIELDS)), static fn (string $v): bool => $v !== '');
    }

    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $apartment
     * @return array<string, mixed>
     */
    private function serviceInput(array $values, array $apartment, string $locale, bool $privacyAccepted): array
    {
        return [
            'apartment_id' => (int) $apartment['id'],
            'check_in' => $values['check_in'],
            'check_out' => $values['check_out'],
            'adults' => $values['adults'],
            'children' => $values['children'] === '' ? '0' : $values['children'],
            'pets' => $values['pets'] === '' ? '0' : $values['pets'],
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'email' => $values['email'],
            'phone' => $values['phone'],
            'notes' => $values['notes'],
            'locale' => $locale,
            'privacy_accepted' => $privacyAccepted,
        ];
    }

    /**
     * Error codes (or already translated messages) -> messages in the visitor's language.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function translate(array $errors, string $locale, array $context = []): array
    {
        $replace = [];
        foreach ($context as $name => $value) {
            if (is_scalar($value)) {
                $replace[(string) $name] = (string) $value;
            }
        }
        $messages = [];
        foreach ($errors as $field => $code) {
            $messages[$field] = Text::has('err.' . $code, $locale) ? Text::get('err.' . $code, $locale, $replace) : $code;
        }
        return $messages;
    }
}
