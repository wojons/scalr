<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Exception\ModelException;

/**
 * @author   Roman Kolodnitskyi  <r.kolodnitskyi@scalr.com>
 *
 * @since    5.9 (10.06.2015)
 *
 * @Entity
 * @Table(name="services_ssl_certs")
 */
class SslCertificate extends AbstractEntity
{
    /**
     * ID.
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * The identifier of the client's environment.
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Certificate's Name.
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Private key.
     *
     * @Column(type="encrypted",name="ssl_pkey")
     * @var string
     */
    public $privateKey;

    /**
     * Private key password.
     *
     * @Column(type="encrypted",nullable=true,name="ssl_pkey_password")
     * @var string
     */
    public $privateKeyPassword;

    /**
     * Certificate.
     *
     * @Column(type="string",name="ssl_cert")
     * @var string
     */
    public $certificate;

    /**
     * CA Bundle.
     *
     * @Column(type="string",nullable=true,name="ssl_cabundle")
     * @var string
     */
    public $caBundle;

    /**
     * Parse certificate and returns its name.
     *
     * @return string
     */
    public function getCertificateName()
    {
        $info = openssl_x509_parse($this->certificate, false);

        return $info['name'] ? $info['name'] : 'uploaded';
    }

    /**
     * Parse certificate and returns its CA Bundle name.
     *
     * @return string
     */
    public function getCaBundleName()
    {
        $info = openssl_x509_parse($this->caBundle, false);

        return $info['name'] ? $info['name'] : 'uploaded';
    }

    /**
     * Get human-readable info about certificate.
     *
     * @return array
     */
    public function getInfo()
    {
        $certInfo = [
            'id' => $this->id,
            'name' => $this->name,
        ];

        $certInfo['privateKey'] = !!$this->privateKey ? 'Private key uploaded' : '';
        $certInfo['privateKeyPassword'] = !!$this->privateKeyPassword ? '******' : '';

        if ($this->certificate) {
            $certInfo['certificate'] = $this->getCertificateName();
        }

        if ($this->caBundle) {
            $certInfo['caBundle'] = $this->getCaBundleName();
        }

        return $certInfo;
    }

    /**
     * {@inheritdoc}
     *
     * @see Scalr\Model\AbstractEntity::delete()
     *
     */
    public function delete()
    {
        $cnt = $this->db()->GetOne("SELECT COUNT(*) FROM apache_vhosts WHERE ssl_cert_id = ?", [$this->id]);
        if ($cnt > 0) {
            throw new ModelException(sprintf('Certificate "%s" is used by %s apache virtual host(s)', $this->name, $cnt));
        }

        parent::delete();
    }

    /**
     * Get list with id and names.
     *
     * @param int $envId Identifier of environment
     * @return array
     */
    public static function getList($envId)
    {
        $result = [];
        $certs = self::findByEnvId($envId);

        foreach ($certs as $cert) {
            $result[] = [
                'id'    => $cert->id,
                'name'  => $cert->name
            ];
        }

        return $result;
    }
}
