<?php

// app/Models/CvShareModel.php

class CvShareModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new share token for a CV.
     */
    public function create(int $cvId, ?string $expiresAt = null): ?string
    {
        $token = bin2hex(random_bytes(32)); // Generate a secure token

        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_shares (cv_id, token, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->bind_param('iss', $cvId, $token, $expiresAt);

        if ($stmt->execute()) {
            return $token;
        }

        return null;
    }

    /**
     * Get a share by token.
     */
    public function getByToken(string $token): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, cv_id, token, expires_at, created_at, updated_at
             FROM cv_shares
             WHERE token = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();

        $result = $stmt->get_result();
        $share = $result->fetch_assoc();

        // Check if expired
        if ($share && $share['expires_at']) {
            $expiresAt = new DateTime($share['expires_at']);
            $now = new DateTime();

            if ($expiresAt < $now) {
                // Token expired, delete it
                $this->delete($share['id']);
                return null;
            }
        }

        return $share ?: null;
    }

    /**
     * Get the share for a CV.
     */
    public function getByCvId(int $cvId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, cv_id, token, expires_at, created_at, updated_at
             FROM cv_shares
             WHERE cv_id = ? AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();

        $result = $stmt->get_result();
        $share = $result->fetch_assoc();

        // Check if expired
        if ($share && $share['expires_at']) {
            $expiresAt = new DateTime($share['expires_at']);
            $now = new DateTime();

            if ($expiresAt < $now) {
                $this->delete((int)$share['id']);
                return null;
            }
        }

        return $share ?: null;
    }

    /**
     * Delete a share (revoke access).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_shares SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $id);

        $ok = $stmt->execute();
        return $ok && $stmt->affected_rows > 0;
    }

    /**
     * Soft-delete share by CV ID.
     */
    public function deleteByCvId(int $cvId): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_shares SET deleted_at = NOW() WHERE cv_id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $cvId);

        return $stmt->execute();
    }

    /**
     * Check if a CV is shared.
     */
    public function isShared(int $cvId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cv_shares WHERE cv_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
