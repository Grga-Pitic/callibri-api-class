<?php


namespace App;

use PDO;
use DateTime;
use GuzzleHttp\Client;

use UnderflowException;
use RuntimeException;
use Exception;


class Callibri
{

    private const REQUEST_DELAY = 1000000;
    private const HTTP_CLIENT_TIMEOUT = 10;

    private $url;

    private $login;
    private $token;

    private $db;
    private $httpClient;

    /**
     * Callibri constructor.
     * @param $url
     * @param $login
     * @param $token
     */
    public function __construct(string $url, string $login, string $token, PDO $db)
    {
        $this->url = $url;
        $this->login = $login;
        $this->token = $token;
        $this->db = $db;

        $this->httpClient = new Client([
            'base_uri' => $url,
            'timeout'  => self::HTTP_CLIENT_TIMEOUT,
            'http_errors' => false,
            'headers' => [
                'Accept'        => 'application/json',
            ]
        ]);

    }

    /**
     * Получить данные о звонках из callibri api за указанный период (не более недели)
     *
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     * @return array
     */
    public function getData(DateTime $dateFrom, DateTime $dateTo)
    {
        $sitesResponse = $this->sendRequest('get_sites');
        $result = [];

        foreach ($sitesResponse['sites'] as $site) {
            $siteStatisticsResponse = $this->sendRequest(
                'site_get_statistics',
                [
                    'site_id' => $site['site_id'],
                    'date1' => $dateFrom->format('d.m.Y'),
                    'date2' => $dateTo->format('d.m.Y'),
                ],
                true
            );

            $result[] = $this->getMergedCalls($siteStatisticsResponse);
        }

        return $result;
    }

    /**
     * Сохранить данные в БД
     *
     * @param array $data
     * @return bool|string
     */
    public function saveData(array $data)
    {
        try {

            $query = $this->getPreparedQuery();

            $this->db->beginTransaction();

            foreach ($data as $row) {
                $stmt = $this->db->prepare($query);
                $fields = $this->getFieldsToInsert($row);
                foreach ($fields as $name => $value) {
                    if (gettype($value) === 'boolean') {
                        $value = ($value) ? 1 : 0;
                    }
                    $stmt->bindValue(':'.$name, $value);
                }
                $stmt->execute();
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            return $e->getMessage();
        }

        return true;
    }

    private function getPreparedQuery()
    {
        return "INSERT INTO CALLS 
                    (date, phone,status,comment,link_download,call_status,name,is_lid,region,accurately,responsible_manager,lid_catcher) 
                    values (str_to_date(:date, '%Y-%m-%dT%H:%i:%s.000Z'),:phone,:status,:comment,:link_download,:call_status,:name,:is_lid,:region,:accurately,:responsible_manager,:lid_catcher)";
    }

    private function getFieldsToInsert($row) {
        return [
            'date'                => $row['date'],
            'phone'               => $row['phone'],
            'status'              => $row['status'],
            'comment'             => $row['comment'],
            'link_download'       => $row['link_download'],
            'call_status'         => $row['call_status'],
            'name'                => $row['name'],
            'is_lid'              => $row['is_lid'],
            'region'              => $row['region'],
            'accurately'          => $row['accurately'],
            'responsible_manager' => $row['responsible_manager'],
            'lid_catcher'         => $row['lid_catcher'],
        ];
    }

    private function getMergedCalls(array $siteStatisticsResponse)
    {
        $callsData = [];

        foreach ($siteStatisticsResponse['channels_statistics'] as $channel) {
            $callsData = array_merge($callsData, $channel['calls']);
        }

        return $callsData;
    }

    private function sendRequest(string $endpoint, array $params = [], bool $safe = false)
    {

        if ($safe) {
            usleep(self::REQUEST_DELAY);
        }

        $params['user_email'] = $this->login;
        $params['user_token'] = $this->token;

        $preparedUrl = "$this->url/$endpoint";

        $response = $this->httpClient->request(
            'GET',
            $preparedUrl,
            ['query' => $params]
        );

        $result = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in response body: ' . $response);
        }

        $this->validate($result);

        return $result;
    }

    private function validate(array $response)
    {
        if ($response['code'] !== 200) {
            throw new Exception('Response returned with code: ' . $response['code']);
        }
    }

}