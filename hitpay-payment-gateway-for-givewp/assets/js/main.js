;(() => {
    const { __ } = window.wp.i18n
    const { createElement } = window.wp.element;

    const hitpayVisual = 'hitpay';

    const ReactElement = (type, props = {}, ...childs) => {
        return Object(createElement)(type, props, ...childs);
    }

    window.givewp.gateways.register({
        id: hitpayVisual,
        async beforeCreatePayment(values) {
            if (values.firstName === 'error') {
                throw new Error('Failed in some way');
            }

            return {
                hitpayIntent: hitpayVisual + '-intent',
            };
        },
        Fields() {
            return ReactElement("span", null, __("You will be redirected to Hitpay Payment Gateway.", "hitpay-give"));
        },
    });
})();