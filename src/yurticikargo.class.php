<?php

class YurticiKargoAPI 
{
    private $wsdl = "http://webservices.yurticikargo.com:8080/KOPSWebServices/ShippingOrderDispatcherServices?wsdl";
    
    // Yurtiçi Kargo Evrensel Test (Sandbox) Bilgileri
    const TEST_USER = 'yurticikargo_api_kullaniciadi';
    const TEST_PASS = 'yurticikargo_api_sifre';

    private $wsUserName;
    private $wsPassword;
    private $userLanguage = "TR";
    private $isTestMode;
    
    /** @var SoapClient|null */
    private $client = null;

    /**
     * Sınıfı Başlatır
     *
     * @param string|null $wsUserName Canlı API Kullanıcı Adı
     * @param string|null $wsPassword Canlı API Şifresi
     * @param bool $isTestMode Test modunu aktif eder (True ise bilgileri girmeye gerek yoktur)
     * @throws Exception Eğer canlı modda bilgiler eksikse hata fırlatır
     */
    public function __construct($wsUserName = null, $wsPassword = null, $isTestMode = false) 
    {
        $this->isTestMode = $isTestMode;

        // Test modu aktifse, bilgileri otomatik olarak test hesaplarıyla eziyoruz
        if ($this->isTestMode === true) {
            $this->wsUserName = self::TEST_USER;
            $this->wsPassword = self::TEST_PASS;
        } else {
            // Canlı mod aktifse, bilgilerin girildiğinden emin oluyoruz
            if (empty($wsUserName) || empty($wsPassword)) {
                throw new Exception("Canlı ortam (Production) için API Kullanıcı Adı ve Şifre girmek zorunludur!");
            }
            $this->wsUserName = $wsUserName;
            $this->wsPassword = $wsPassword;
        }
    }

    /**
     * Sadece ihtiyaç olduğunda SoapClient'i başlatır
     */
    private function getClient() 
    {
        if ($this->client === null) {
            $this->client = new SoapClient($this->wsdl, array(
                "trace"              => 1,
                "exceptions"         => 1,
                "connection_timeout" => 15,
                "stream_context"     => stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                    ]
                ])
            ));
        }
        return $this->client;
    }

    /**
     * Kargo Gönderisi Oluşturur
     */
    public function createShipment($orderData) 
    {
        try {
            $shipmentData = array(
                'wsUserName'      => $this->wsUserName,
                'wsPassword'      => $this->wsPassword,
                'userLanguage'    => $this->userLanguage,
                'ShippingOrderVO' => array(
                    'cargoKey'         => $orderData['cargoKey'],
                    'invoiceKey'       => $orderData['invoiceKey'] ?? '',
                    'receiverCustName' => $orderData['name'],
                    'receiverAddress'  => $orderData['address'],
                    'receiverPhone1'   => $this->formatPhone($orderData['phone']),
                    'cityName'         => $this->formatText($orderData['city']),
                    'townName'         => $this->formatText($orderData['district']),
                    'desi'             => $orderData['desi'] ?? 1,
                    'cargoCount'       => $orderData['cargoCount'] ?? 1,
                    
                    'taxOfficeId'        => $orderData['taxOfficeId'] ?? '',
                    'taxNumber'          => $orderData['taxNumber'] ?? '',
                    'taxOfficeName'      => $orderData['taxOfficeName'] ?? '',
                    'waybillNo'          => $orderData['waybillNo'] ?? '',
                    'specialField1'      => $this->isTestMode ? 'TEST_VERISI' : '', 
                    'specialField2'      => '',
                    'specialField3'      => '',
                    'ttDocumentId'       => '', 
                    'ttDocumentSaveType' => '', 
                    'dcSelectedCredit'   => '', 
                    'dcCreditRule'       => '',
                    'description'        => '',
                    'orgReceiverCustId'  => '',
                    'isWorldECommerce'   => '0',
                    'receiverPhone2'     => '',
                    'receiverPhone3'     => ''
                )
            );

            $response = $this->getClient()->createShipment($shipmentData);
            return $this->parseResponse($response, 'ShippingOrderResultVO');

        } catch (SoapFault $e) {
            return $this->errorResponse("SOAP Hatası: " . $e->getMessage());
        }
    }

    /**
     * Kargo Durumu Sorgular
     */
    public function queryShipment($cargoKey) 
    {
        try {
            $queryData = array(
                'wsUserName'        => $this->wsUserName,
                'wsPassword'        => $this->wsPassword,
                'wsLanguage'        => $this->userLanguage,
                'keys'              => $cargoKey,
                'keyType'           => 0,
                'addHistoricalData' => false,
                'onlyTracking'      => false
            );

            $response = $this->getClient()->queryShipment($queryData);
            return $this->parseResponse($response, 'ShippingDeliveryVO');

        } catch (SoapFault $e) {
            return $this->errorResponse("SOAP Sorgu Hatası: " . $e->getMessage());
        }
    }

    /**
     * Kargo Siparişini İptal Eder
     */
    public function cancelShipment($cargoKey) 
    {
        try {
            $cancelData = array(
                'wsUserName'   => $this->wsUserName,
                'wsPassword'   => $this->wsPassword,
                'userLanguage' => $this->userLanguage,
                'cargoKeys'    => $cargoKey
            );

            $response = $this->getClient()->cancelShipment($cancelData);
            return $this->parseResponse($response, 'ShippingOrderResultVO');

        } catch (SoapFault $e) {
            return $this->errorResponse("SOAP İptal Hatası: " . $e->getMessage());
        }
    }

    /**
     * Test modunun aktif olup olmadığını döndürür
     */
    public function isTestModeActive() 
    {
        return $this->isTestMode;
    }

    /**
     * Hata ayıklama için son XML loglarını döndürür
     */
    public function getLastLogs() 
    {
        if ($this->client) {
            return array(
                'request'  => $this->client->__getLastRequest(),
                'response' => $this->client->__getLastResponse()
            );
        }
        return array('request' => '', 'response' => '');
    }

    // ==========================================
    // YARDIMCI METOTLAR
    // ==========================================

    private function parseResponse($response, $resultKey) 
    {
        if (isset($response->$resultKey)) {
            $result = $response->$resultKey;
            
            if (isset($result->outFlag) && $result->outFlag == "0") {
                return array(
                    'status'  => true,
                    'message' => $result->outResult ?? 'İşlem Başarılı',
                    'data'    => $result
                );
            } else {
                return $this->errorResponse($result->outResult ?? 'Bilinmeyen API Hatası', $result->outFlag ?? null);
            }
        }
        return $this->errorResponse("API'den geçersiz bir yanıt döndü.");
    }

    private function errorResponse($message, $code = null) 
    {
        return array('status' => false, 'message' => $message, 'code' => $code, 'data' => null);
    }

    private function formatPhone($phone) 
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    private function formatText($text) 
    {
        return mb_strtoupper(trim($text), 'UTF-8');
    }
}
