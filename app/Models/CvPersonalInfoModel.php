<?php

/**
 * app/Models/CvPersonalInfoModel.php
 *
 * Model for the cv_personal_info table.
 * Handles structured storage of CV personal information
 * extracted from the builder_data JSON column.
 */

class CvPersonalInfoModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Get personal info for a CV.
     */
    public function getByCvId(int $cvId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cv_personal_info WHERE cv_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Get personal info for a user (latest CV).
     */
    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT pi.* FROM cv_personal_info pi
             JOIN cvs c ON pi.cv_id = c.id
             WHERE pi.user_id = ?
             WHERE pi.deleted_at IS NULL AND c.deleted_at IS NULL
             ORDER BY c.updated_at DESC
             LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Save or update personal info for a CV.
     * Uses INSERT ON DUPLICATE KEY UPDATE for idempotency.
     */
    public function save(int $cvId, int $userId, array $data): bool
    {
        $fields = [
            'cv_id' => $cvId,
            'user_id' => $userId,
            'full_name' => $data['full_name'] ?? '',
            'job_title' => $data['job_title'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'nationality' => $data['nationality'] ?? '',
            'gender' => !empty($data['gender']) ? $data['gender'] : null,
            'driving_license' => $data['driving_license'] ?? '',
            'website' => $data['website'] ?? '',
            'linkedin' => $data['linkedin'] ?? '',
            'github' => $data['github'] ?? '',
            'twitter' => $data['twitter'] ?? '',
            'portfolio' => $data['portfolio'] ?? '',
            'national_id_no' => $data['national_id_no'] ?? '',
            'passport_no' => $data['passport_no'] ?? '',
            'birth_certificate_no' => $data['birth_certificate_no'] ?? '',
            'religion' => $data['religion'] ?? '',
        ];

        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_personal_info
             (cv_id, user_id, full_name, job_title, email, phone, address,
              date_of_birth, nationality, gender, driving_license,
              website, linkedin, github, twitter, portfolio,
              national_id_no, passport_no, birth_certificate_no, religion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             full_name = VALUES(full_name),
             job_title = VALUES(job_title),
             email = VALUES(email),
             phone = VALUES(phone),
             address = VALUES(address),
             date_of_birth = VALUES(date_of_birth),
             nationality = VALUES(nationality),
             gender = VALUES(gender),
             driving_license = VALUES(driving_license),
             website = VALUES(website),
             linkedin = VALUES(linkedin),
             github = VALUES(github),
             twitter = VALUES(twitter),
             portfolio = VALUES(portfolio),
             national_id_no = VALUES(national_id_no),
             passport_no = VALUES(passport_no),
             birth_certificate_no = VALUES(birth_certificate_no),
             religion = VALUES(religion)"
        );

        $dob = $fields['date_of_birth'];
        $gender = $fields['gender'];

        $stmt->bind_param(
            'iissssssssssssssssss',
            $fields['cv_id'],
            $fields['user_id'],
            $fields['full_name'],
            $fields['job_title'],
            $fields['email'],
            $fields['phone'],
            $fields['address'],
            $dob,
            $fields['nationality'],
            $gender,
            $fields['driving_license'],
            $fields['website'],
            $fields['linkedin'],
            $fields['github'],
            $fields['twitter'],
            $fields['portfolio'],
            $fields['national_id_no'],
            $fields['passport_no'],
            $fields['birth_certificate_no'],
            $fields['religion']
        );

        return $stmt->execute();
    }

    /**
     * Delete personal info for a CV.
     */
    public function deleteByCvId(int $cvId): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_personal_info SET deleted_at = NOW() WHERE cv_id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $cvId);
        return $stmt->execute();
    }

    /**
     * Soft-delete personal info for a user.
     */
    public function deleteByUserId(int $userId): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_personal_info SET deleted_at = NOW() WHERE user_id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }

    /**
     * Extract personal info from builder_data JSON structure.
     *
     * The builder_data stores personal info at $data['personal'].
     * Returns a flat array suitable for save().
     */
    public static function extractFromBuilderData(array $builderData): array
    {
        $personal = $builderData['personal'] ?? [];

        return [
            'full_name' => $personal['full_name'] ?? '',
            'job_title' => $personal['job_title'] ?? '',
            'email' => $personal['email'] ?? '',
            'phone' => $personal['phone'] ?? '',
            'address' => $personal['address'] ?? '',
            'date_of_birth' => $personal['date_of_birth'] ?? '',
            'nationality' => $personal['nationality'] ?? '',
            'gender' => $personal['gender'] ?? '',
            'driving_license' => $personal['driving_license'] ?? '',
            'website' => $personal['website'] ?? '',
            'linkedin' => $personal['linkedin'] ?? '',
            'github' => $personal['github'] ?? '',
            'twitter' => $personal['twitter'] ?? '',
            'portfolio' => $personal['portfolio'] ?? '',
            'national_id_no' => $personal['national_id_no'] ?? '',
            'passport_no' => $personal['passport_no'] ?? '',
            'birth_certificate_no' => $personal['birth_certificate_no'] ?? '',
            'religion' => $personal['religion'] ?? '',
        ];
    }

    /**
     * Count total records in the table.
     */
    public function count(): int
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM cv_personal_info WHERE deleted_at IS NULL");
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Check if a CV has personal info in the new table.
     */
    public function exists(int $cvId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cv_personal_info WHERE cv_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
