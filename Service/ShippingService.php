<?php

namespace CleverAge\ColissimoBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use CleverAge\ColissimoBundle\Model\Shipping\Label;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use CleverAge\ColissimoBundle\Model\Shipping\OutputFormat;
use CleverAge\ColissimoBundle\Model\Shipping\Letter\Sender;
use CleverAge\ColissimoBundle\Exception\ShippingRequestException;
use CleverAge\ColissimoBundle\Model\Shipping\Enum\LabelFileType;
use CleverAge\ColissimoBundle\Model\Shipping\Response\LabelResponse;

class ShippingService extends AbstractService
{
    private const URL = '/sls-ws/SlsServiceWSRest/2.0/generateLabel';
    private $outputFormat = [
        'x' => 10,
        'y' => 10,
        'outputPrintingType' => 'ZPL_10x15_203dpi',
    ];

    public function call(Label $label, array $customCredentials = []): LabelResponse
    {
        $letter = $label->getLetter();
        if (null === $letter->getSender()) {
            $this->manageSender($label);
        }

        $service = $letter->getService();
        if (null === $service->getCommercialName()) {
            $service->setCommercialName($this->service['commercialName'] ?? '');
        }
            $this->outputFormat = $label->getOutputFormat()->toArray();

        return $this->doCall(Request::METHOD_POST, self::URL, $label->toArray(), $customCredentials);
    }

    private function manageSender(Label $label): void
    {
        $sender = (new Sender())
            ->setCompanyName($this->sender['companyName'] ?? '')
            ->setLine0($this->sender['line0'] ?? '')->setLine1($this->sender['line1'] ?? '')
            ->setLine2($this->sender['line2'] ?? '')->setLine3($this->sender['line3'] ?? '')
            ->setCountryCode($this->sender['countryCode'] ?? '')
            ->setZipCode($this->sender['zipCode'] ?? '')
            ->setCity($this->sender['city'] ?? '');

        $label->getLetter()->setSender($sender);
    }

    public function parseResponse($response): LabelResponse
    {
        // dd($this->outputFormat);
        $responses = $this->slsResponseParser->parse($response);
        $labelV2Response = $responses[0]['body']['labelV2Response'];
        if (null === $labelV2Response) {
            $errors = [];
            foreach ($responses[0]['body']['messages'] as $message) {
                $errors[] = $message['messageContent'] . ', ';
            }


            throw new ShippingRequestException(implode('', $errors));
        }
        if (isset($responses[1]["body"])) {
            $labelV2Response['labelFilePath'] = $labelV2Response['labelFilePath'] ?? $this->uploadLabel($responses[1]["body"]);
        }

        return (new LabelResponse())->populate($labelV2Response);
    }

    public function validateDataBeforeCall(array $dataToValidate): void
    {
    }

    public function parseErrorCodeAndThrow(int $errorCode): void
    {
    }

    public function uploadLabel($fileString)
    {
        //'outputPrintingType' => 'PDF_10x15_203dpi',
        $fileType = explode('_',$this->outputFormat['outputPrintingType'])[0];
        $fileType = constant(LabelFileType::class . '::' . $fileType);
            // Créer un nom de fichier unique pour éviter les conflits
            $fileName = md5(uniqid()) . '.' . $fileType['extension'];
            
            // Créer un fichier temporaire avec la chaîne ZPL
            $tempFilePath = sys_get_temp_dir() . '/' . $fileName;
            file_put_contents($tempFilePath, $fileString);

            //check if file is valid
            if (!file_exists($tempFilePath)) {
                throw new \Exception('Invalid file');
            }
            
            
            $destinationPath = $this->labelUploadDir .'/' . $fileType['extension'] . '/';
            //create directory if not exists with 775 permission
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0775, true);
            }

            $destinationFile = $destinationPath . $fileName;
            // Copy the temporary file to the destination path
            if (!copy($tempFilePath, $destinationFile)) {
                throw new \Exception('Failed to upload file');
            }
            
            unlink($tempFilePath);
            return  $fileType['extension'] . '/' .$fileName;
    }
}
