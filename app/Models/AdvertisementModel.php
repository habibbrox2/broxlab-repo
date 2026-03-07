<?php
// classes/AdvertisementModel.php

class AdvertisementModel {
    private $mysqli;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new advertisement inquiry
     * 
     * @param string $name - Advertiser's name
     * @param string $email - Advertiser's email
     * @param string $company - Company name
     * @param string $budget - Budget information
     * @param string $message - Message/inquiry details
     * @param string $ip - IP address of the advertiser
     * @return int|false - ID of created inquiry or false on failure
     */
    public function createInquiry($name, $email, $company, $budget, $message, $ip) {
        try {
            $sql = "INSERT INTO advertisement_inquiries 
                    (name, email, company, budget, message, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                logError("AdvertisementModel::createInquiry - Prepare failed: " . $this->mysqli->error);
                return false;
            }
            
            $stmt->bind_param("ssssss", $name, $email, $company, $budget, $message, $ip);
            
            if (!$stmt->execute()) {
                logError("AdvertisementModel::createInquiry - Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }
            
            $inquiryId = $stmt->insert_id;
            $stmt->close();
            
            return $inquiryId;
        } catch (Exception $e) {
            logError("AdvertisementModel::createInquiry - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all advertisement inquiries (for admin)
     * 
     * @return array - Array of inquiries
     */
    public function getAllInquiries() {
        try {
            $sql = "SELECT * FROM advertisement_inquiries ORDER BY created_at DESC";
            $result = $this->mysqli->query($sql);
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError("AdvertisementModel::getAllInquiries - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific advertisement inquiry by ID
     * 
     * @param int $id - Inquiry ID
     * @return array|null - Inquiry data or null
     */
    public function getInquiryById($id) {
        try {
            $sql = "SELECT * FROM advertisement_inquiries WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $inquiry = $result->fetch_assoc();
            $stmt->close();
            return $inquiry;
        } catch (Exception $e) {
            logError("AdvertisementModel::getInquiryById - " . $e->getMessage());
            return null;
        }
    }

}
