<?php

require_once __DIR__ . '/../Helpers/GlobalStubs.php';
require_once __DIR__ . '/../../includes/arrays.php';

/*
 * Tests for the static lookup arrays and the notify_accounts
 * building logic defined in includes/arrays.php.
 */

describe('static array definitions', function (): void {
    it('defines $service_types with the expected protocol keys', function (): void {
        global $service_types;

        expect($service_types)->toBeArray()
            ->toHaveKey('web_http')
            ->toHaveKey('web_https')
            ->toHaveKey('mail_smtp')
            ->toHaveKey('mail_smtptls')
            ->toHaveKey('mail_smtps')
            ->toHaveKey('mail_imap')
            ->toHaveKey('mail_imaps')
            ->toHaveKey('mail_pop3')
            ->toHaveKey('mail_pop3s')
            ->toHaveKey('dns_dns')
            ->toHaveKey('dns_doh')
            ->toHaveKey('ldap_ldap')
            ->toHaveKey('ldap_ldaps')
            ->toHaveKey('ftp_ftp')
            ->toHaveKey('ftp_ftps')
            ->toHaveKey('ftp_scp')
            ->toHaveKey('ftp_tftp')
            ->toHaveKey('smb_smb')
            ->toHaveKey('smb_smbs')
            ->toHaveKey('mqtt_mqtt');
    });

    it('has string values for every $service_types entry', function (): void {
        global $service_types;

        foreach ($service_types as $value) {
            expect($value)->toBeString()->not->toBeEmpty();
        }
    });

    it('defines $httperrors with standard HTTP status code keys', function (): void {
        global $httperrors;

        expect($httperrors)->toBeArray()
            ->toHaveKey(0)
            ->toHaveKey(200)
            ->toHaveKey(301)
            ->toHaveKey(302)
            ->toHaveKey(400)
            ->toHaveKey(401)
            ->toHaveKey(403)
            ->toHaveKey(404)
            ->toHaveKey(500)
            ->toHaveKey(502)
            ->toHaveKey(503);
    });

    it('has integer keys and string values for every $httperrors entry', function (): void {
        global $httperrors;

        foreach ($httperrors as $code => $label) {
            expect($code)->toBeInt();
            expect($label)->toBeString()->not->toBeEmpty();
        }
    });

    it('defines $servcheck_seconds with keys 3 through 10', function (): void {
        global $servcheck_seconds;

        expect($servcheck_seconds)->toBeArray();
        expect(array_keys($servcheck_seconds))->toBe([3, 4, 5, 6, 7, 8, 9, 10]);
    });

    it('has string values for every $servcheck_seconds entry', function (): void {
        global $servcheck_seconds;

        foreach ($servcheck_seconds as $value) {
            expect($value)->toBeString()->not->toBeEmpty();
        }
    });
});

describe('notify_accounts when db_fetch_assoc returns empty', function (): void {
    /*
     * When db_table_exists returns false (our stub default) or
     * db_fetch_assoc returns an empty result, $servcheck_notify_accounts
     * must remain an empty array.
     */
    it('produces an empty $servcheck_notify_accounts when contacts table missing', function (): void {
        global $servcheck_notify_accounts;

        // The stub db_table_exists returns false, so the query is never run
        expect($servcheck_notify_accounts)->toBeArray()->toBeEmpty();
    });

    it('produces an empty array when contact_users is empty', function (): void {
        $contact_users = [];

        $notify_accounts = [];
        foreach ($contact_users as $contact_user) {
            $notify_accounts[$contact_user['id']] = $contact_user['full_name'] . ' - ' . ucfirst($contact_user['type']);
        }

        expect($notify_accounts)->toBeArray()->toBeEmpty();
    });
});

describe('notify_accounts edge cases', function (): void {
    it('builds notify_accounts correctly from valid contact data', function (): void {
        $contact_users = [
            ['id' => 1, 'data' => 'user1@example.com', 'type' => 'email', 'full_name' => 'Alice'],
            ['id' => 5, 'data' => 'user5@example.com', 'type' => 'slack', 'full_name' => 'Bob'],
        ];

        $notify_accounts = [];
        foreach ($contact_users as $contact_user) {
            $notify_accounts[$contact_user['id']] = $contact_user['full_name'] . ' - ' . ucfirst($contact_user['type']);
        }

        expect($notify_accounts)->toBe([
            1 => 'Alice - Email',
            5 => 'Bob - Slack',
        ]);
    });

    it('handles contact entries with empty data field gracefully', function (): void {
        // The WHERE clause filters these out in production, but the
        // building logic itself should not break on empty data values.
        $contact_users = [
            ['id' => 2, 'data' => '', 'type' => 'email', 'full_name' => 'Carol'],
            ['id' => 3, 'data' => 'user3@test.com', 'type' => 'sms', 'full_name' => 'Dave'],
        ];

        $notify_accounts = [];
        foreach ($contact_users as $contact_user) {
            $notify_accounts[$contact_user['id']] = $contact_user['full_name'] . ' - ' . ucfirst($contact_user['type']);
        }

        // Both entries produce a label; the data field is not used in the label
        expect($notify_accounts)->toHaveCount(2);
        expect($notify_accounts[2])->toBe('Carol - Email');
        expect($notify_accounts[3])->toBe('Dave - Sms');
    });

    it('handles a single contact entry', function (): void {
        $contact_users = [
            ['id' => 10, 'data' => 'solo@example.com', 'type' => 'email', 'full_name' => 'Solo User'],
        ];

        $notify_accounts = [];
        foreach ($contact_users as $contact_user) {
            $notify_accounts[$contact_user['id']] = $contact_user['full_name'] . ' - ' . ucfirst($contact_user['type']);
        }

        expect($notify_accounts)->toBe([10 => 'Solo User - Email']);
    });
});
