<?php
class LoewixAuth {
    private $centerUrl = "http://localhost/loewix_center/public/api/check_user.php";
    private $secretKey = "KUNCI_RAHASIA_LOEWIX_CENTER_2025";

    public function validateUser($token) {
        $ch = curl_init();
        
        $postData = [
            'secret_key' => $this->secretKey,
            'token' => $token
        ];

        curl_setopt($ch, CURLOPT_URL, $this->centerUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === 'success') {
                return $result['data'];
            }
        }
        
        return false;
    }
}