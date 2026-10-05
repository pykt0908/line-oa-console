<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\LineAccount;
use App\Models\LineIdentity;
use App\Models\Setting;
use App\Services\Line\LineProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(
        private LineProfileService $profiles,
        private AuditService $audit,
    ) {}

    public function findOrCreateIdentity(LineAccount $account, string $lineUserId): LineIdentity
    {
        $identity = LineIdentity::query()
            ->where('line_account_id', $account->id)
            ->where('line_user_id', $lineUserId)
            ->first();

        if ($identity) {
            $identity->forceFill(['last_seen_at' => now()])->save();

            return $identity->load('contact');
        }

        $profile = $this->profiles->getProfile($account, $lineUserId);

        return DB::transaction(function () use ($account, $lineUserId, $profile) {
            $contact = Contact::query()->create([
                'student_code' => null,
                'contact_type' => 'unknown',
            ]);

            $identity = LineIdentity::query()->create([
                'contact_id' => $contact->id,
                'line_account_id' => $account->id,
                'line_user_id' => $lineUserId,
                'display_name' => $profile['displayName'] ?? null,
                'picture_url' => $profile['pictureUrl'] ?? null,
                'status_message' => $profile['statusMessage'] ?? null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            return $identity->load('contact');
        });
    }

    public function setStudentCode(Contact $contact, string $studentCode, bool $forceMerge = false): array
    {
        $studentCode = trim($studentCode);

        if ($studentCode === '') {
            throw ValidationException::withMessages([
                'student_code' => 'กรุณาระบุรหัสนักศึกษา',
            ]);
        }

        if (! preg_match('/^\d{1,11}$/', $studentCode)) {
            throw ValidationException::withMessages([
                'student_code' => 'รหัสนักศึกษาต้องเป็นตัวเลข ไม่เกิน 11 หลัก',
            ]);
        }

        $existing = Contact::query()
            ->where('student_code', $studentCode)
            ->whereNull('merged_into_contact_id')
            ->where('id', '!=', $contact->id)
            ->first();

        if ($existing && ! $forceMerge) {
            return [
                'status' => 'duplicate',
                'existing_contact' => $existing->load('identities.lineAccount'),
            ];
        }

        if ($existing && $forceMerge) {
            $merged = $this->merge($existing, $contact);
            $this->audit->log('contact.merge', $merged, [
                'source_contact_id' => $contact->id,
                'target_contact_id' => $existing->id,
                'student_code' => $studentCode,
            ]);

            return ['status' => 'merged', 'contact' => $merged->fresh(['identities.lineAccount'])];
        }

        $contact->update([
            'student_code' => $studentCode,
            'contact_type' => 'student',
        ]);

        $this->audit->log('contact.student_code', $contact, [
            'student_code' => $studentCode,
        ]);

        return ['status' => 'updated', 'contact' => $contact->fresh(['identities.lineAccount'])];
    }

    public function merge(Contact $target, Contact $source): Contact
    {
        if ($target->id === $source->id) {
            throw ValidationException::withMessages([
                'merge' => 'ไม่สามารถเชื่อม Contact กับตัวเองได้',
            ]);
        }

        if ($source->isMerged() || $target->isMerged()) {
            throw ValidationException::withMessages([
                'merge' => 'Contact นี้ถูกเชื่อมไปแล้ว',
            ]);
        }

        return DB::transaction(function () use ($target, $source) {
            LineIdentity::query()
                ->where('contact_id', $source->id)
                ->update(['contact_id' => $target->id]);

            \App\Models\Conversation::query()
                ->where('contact_id', $source->id)
                ->update(['contact_id' => $target->id]);

            if (! $target->student_code && $source->student_code) {
                $target->student_code = $source->student_code;
                $target->contact_type = 'student';
            }

            if (! $target->note && $source->note) {
                $target->note = $source->note;
            }

            $target->save();

            $source->update([
                'merged_into_contact_id' => $target->id,
                'merged_at' => now(),
            ]);

            return $target->fresh(['identities.lineAccount', 'conversations']);
        });
    }

    public function shouldAskStudentCode(): bool
    {
        return Setting::getBool('contact.ask_student_code', false);
    }
}
