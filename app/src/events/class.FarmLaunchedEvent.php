<?php

class FarmLaunchedEvent extends AbstractServerEvent
{
    /**
     * Whether it should mark instance as active
     *
     * @var bool
     */
    public $MarkInstancesAsActive;

    /**
     * Identifier of the User
     *
     * @var int
     */
    public $userId;

    /**
     * Audit log extra fields
     *
     * @var array
     */
    public $auditLogExtra;

    /**
     * Constructor
     *
     * @param   bool    $MarkInstancesAsActive
     *          Whether it should mark instance as Active
     *
     * @param   int $userId optional
     *          Identifier of the User
     *
     * @param   array $auditLogExtra optional
     *          Audit log extra fields
     */
    public function __construct($MarkInstancesAsActive, $userId = null, array $audutLogExtra = null)
    {
        parent::__construct();

        $this->MarkInstancesAsActive = $MarkInstancesAsActive;

        $this->userId = $userId;

        $this->auditLogExtra = $audutLogExtra ?: [];
    }

    /**
     * {@inheritdoc}
     * @see AbstractServerEvent::getTextDetails()
     */
    public function getTextDetails()
    {
        return "Farm has been launched";
    }
}
