<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

class GuestDirectory
{
    public function __construct(private readonly BaseConnection $db)
    {
        helper('marketing_analytics');
    }

    /**
     * Menambah tamu baru atau memperbarui tamu lama berdasarkan ID, telepon, atau email.
     */
    public function save(array $data, ?int $selectedGuestId = null): int
    {
        $guest = [
            'property_id' => PropertyContext::activeId(),
            'full_name' => trim((string) ($data['full_name'] ?? '')),
            'email' => $this->nullable($data['email'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'origin' => $this->normalizedOrigin($data['origin'] ?? null),
            'notes' => $this->nullable($data['notes'] ?? null),
            'ktp_photo_path' => $this->nullable($data['ktp_photo_path'] ?? null),
            'ktp_photo_original_name' => $this->nullable($data['ktp_photo_original_name'] ?? null),
            'ktp_photo_mime_type' => $this->nullable($data['ktp_photo_mime_type'] ?? null),
            'ktp_photo_size' => isset($data['ktp_photo_size']) ? (int) $data['ktp_photo_size'] : null,
        ];
        $guestId = $selectedGuestId !== null && $selectedGuestId > 0
            ? $this->findGuestId($selectedGuestId)
            : $this->findExistingId($guest['phone'], $guest['email']);
        $now = date('Y-m-d H:i:s');

        if ($guestId !== null) {
            $updates = ['full_name' => $guest['full_name'], 'updated_at' => $now];
            foreach (['email', 'phone', 'origin', 'notes', 'ktp_photo_path', 'ktp_photo_original_name', 'ktp_photo_mime_type', 'ktp_photo_size'] as $field) {
                if ($guest[$field] !== null) {
                    $updates[$field] = $guest[$field];
                }
            }
            $this->db->table('guests')->where('id', $guestId)->where('property_id', PropertyContext::activeId())->update($updates);

            return $guestId;
        }

        $this->db->table('guests')->insert($guest + ['created_at' => $now]);

        return (int) $this->db->insertID();
    }

    private function findGuestId(int $guestId): ?int
    {
        $row = $this->db->table('guests')->select('id')->where('id', $guestId)->where('property_id', PropertyContext::activeId())->get()->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function findExistingId(?string $phone, ?string $email): ?int
    {
        if ($phone !== null) {
            $needle = canonical_guest_phone($phone);
            if ($needle !== null) {
                $rows = $this->db->table('guests')
                    ->select('id, phone')
                    ->where('property_id', PropertyContext::activeId())
                    ->where('phone IS NOT NULL', null, false)
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    if (canonical_guest_phone($row['phone']) === $needle) {
                        return (int) $row['id'];
                    }
                }
            }
        }

        if ($email !== null) {
            $row = $this->db->table('guests')
                ->select('id')
                ->where('property_id', PropertyContext::activeId())
                ->where('LOWER(email)', canonical_guest_email($email))
                ->get()
                ->getRowArray();

            if ($row !== null) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizedOrigin(mixed $value): ?string
    {
        $origin = normalize_guest_origin($value);

        return $origin === 'Tidak diketahui' ? null : $origin;
    }
}
