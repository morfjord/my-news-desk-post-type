<?php

class MNDPT_Admin_Notice {
    private $_message;
    private $_type;
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';

    function __construct( $message, $type = self::TYPE_SUCCESS ) {
        $this->_message = $message;
        $this->_type = $type;

        add_action( 'admin_notices', array( $this, 'render' ) );
    }

    function render() {
        $class = $this->get_classes();
        $message = $this->_message;
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));

    }

    function get_classes()
    {
        if( self::TYPE_SUCCESS == $this->_type)
            return 'notice notice-success is-dismissible';

        if (self::TYPE_ERROR == $this->_type)
            return 'notice notice-error is-dismissible';

        return 'notice is-dismissible';

    }

}