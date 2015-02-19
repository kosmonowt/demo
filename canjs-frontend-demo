/** CONFIG **/
var sUrl = "/app/";
var appConfig = {
  product: {
    search: {
      term: {
        autocompleteLength : 3 // Begin to search with Autocomplete
      }
    }
  }
};

/** PUT TO LIB SOON **/
function ucfirst(str) {
  //  discuss at: http://phpjs.org/functions/ucfirst/
  // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Onno Marsman
  // improved by: Brett Zamir (http://brett-zamir.me)
  //   example 1: ucfirst('kevin van zonneveld');
  //   returns 1: 'Kevin van zonneveld'

  str += '';
  var f = str.charAt(0)
    .toUpperCase();
  return f + str.substr(1);
}

/** END LIB **/


/** ORDERS **/
var Order = can.Model.extend({
  findAll: 'GET '+sUrl+'orders',
  findOne: 'GET '+sUrl+'orders/{id}',
  update: 'PUT '+sUrl+'orders/{id}',
  destroy: 'DELETE '+sUrl+'orders/{id}'
}, {});

/** ORDER STATE **/
var OrderState = can.Model.extend({
  findAll: 'GET '+sUrl+'orderStates',
  findOne: 'GET '+sUrl+'orderStates/{id}',
  update: 'PUT '+sUrl+'orderStates/{id}',
  destroy: 'DELETE '+sUrl+'orderStates/{id}'
}, {});

var Merchant = can.Model.extend({
  findAll: 'GET '+sUrl+'merchants',
  findOne: 'GET '+sUrl+'merchants/{id}',
  update: 'PUT '+sUrl+'merchants/{id}',
  destroy: 'DELETE '+sUrl+'merchants/{id}'
}, {});

var ProductSearch = can.Model.extend({
  findAll: function(params) {
    return $.get(sUrl+"products/search/"+params.merchantId+"/"+params.term);
  },
  findOne: 'GET '+sUrl+'products/{id}',
}, {});

var User = can.Model.extend({
  findAll: 'GET '+sUrl+'users',
  findOne: 'GET '+sUrl+'users/{id}',
  update: 'PUT '+sUrl+'users/{id}',
  destroy: 'DELETE '+sUrl+'users/{id}',
  create : 'POST '+sUrl+'users'
}, {});

var Member = can.Model.extend({
  findAll: 'GET '+sUrl+'members',
  findOne: 'GET '+sUrl+'members/{id}',
  update: 'PUT '+sUrl+'members/{id}',
  destroy: 'DELETE '+sUrl+'members/{id}',
  create : 'POST '+sUrl+'members'
}, {});

var MemberGroup = can.Model.extend({
  findAll: 'GET '+sUrl+'memberGroups',
  findOne: 'GET '+sUrl+'memberGroups/{id}',
  update: 'PUT '+sUrl+'memberGroups/{id}',
  destroy: 'DELETE '+sUrl+'memberGroups/{id}'
}, {});

var MemberStatus = can.Model.extend({
  findAll: 'GET '+sUrl+'memberStatus',
  findOne: 'GET '+sUrl+'memberStatus/{id}',
  update: 'PUT '+sUrl+'memberStatus/{id}',
  destroy: 'DELETE '+sUrl+'memberStatus/{id}'
}, {});

can.mustache.registerHelper('quickEdit',
  function(subject, verb, number, options){
});

var handleRestError = function(error) {
  console.log(error.statusText);
  console.log(error.responseText);
};

can.Component.extend({
  tag: 'members-app',
  scope: {
    members: new Member.List({}),
    memberGroups: new MemberGroup.List({}),
    memberStatus: new MemberStatus.List({}),
    memberUsers: new can.List({}),
    select: function(members){
      this.attr('selectedMember', members);
    },
    delete: function(member) {
      if (confirm("Willst Du "+member.name+" wirklich löschen?")) member.destroy();
    },
    save: function(member) {
      member.save();
      this.removeAttr('selectedMember');
    },
    submitMember: function(scope,el,ev) {
      ev.preventDefault();
      // Auto assign all formfields
      var data = {};
      el.find("input, select").each(function(i,x){
        eval("data."+$(this).attr("name")+" = '"+$(this).val()+"';"); // Save Data
      });
      var member = new Member(data);
      member.save(
        function(member){ scope.members.push(member);},  // Success
        handleRestError // Error
        );

    },

    filterUsersByMember: function(m,el,ev) { this.memberUsers.replace(m.user); },

    /** 
     * Edit functions.
     * Has to move to a global prototype soon.
     */  
    editAttr: function(m,el,ev) { el.parent().toggle().siblings().toggle(); },
    editSubmit: function(m,el,ev) {
      /* This Function needs a structure: button MUST be sibling of input or other form field, ATTR value must be defined by name attribute in input or form field */
      el.parent().toggle().siblings().toggle();
      var input = el.siblings('.editValue');
      var val = input.val();
      var attrName = input.attr("name");

      if (typeof(el.data("scope")) != "undefined") {
        // for <Select>, need to do like this unless I found way to ascend the sections
        var modelList = eval("this."+el.data("scope"));
        var modelId = el.parents(".can-id").data("id");
        var modelRelated = el.parents(".can-id").data("model"); // the name of the current model (i.E. "member_group" when we want to change "member")

        modelList.each(function(model, index) {
          if (model.id == modelId) {
            model.attr(attrName,el.prev().val());
            model.attr(modelRelated,m);
            model.save();
          }
          return false;
        });
      } else {
        // for <Input>, quite easy
        m.attr(attrName,val);
        if (typeof(m.save)!= "undefined" ) {
          m.save();
        } else {
          //retrieve model
          var modelName = el.parents(".modelSection").data("model");
          eval("var u = new "+modelName+"(m)");
          u.save();
          // SAVE
        }
      }
    }
  }
});

var template = can.view("appMustache");
$("body").html(template);


AppComponent.extend({
  tag: 'orders-app',
  scope: {
    orders: new Order.List({}),
    orderStates: new OrderState.List({}),
    merchants: new Merchant.List({}),
    productSearch: null,
    productSearchTerm: "",
    toggle: function(b,el,ev) { // Bestellung, Element, Event
      var oldStatus = b.order_state_id;
      b.attr("order_state_id", el.data("order_state_id"));
      b.save();
    },
    findProduct: function(p,el,ev) {
      var acLength = appConfig.product.search.term.autocompleteLength;
      var merchantId = $('#newOrderFormMerchantId').val();
      var val = el.val();
      if (val.length < acLength) return;

      if ( (this.productSearchTerm == "") || (this.productSearchTerm.substr(0,acLength) != val.substr(0,acLength) ) ) {
        this.productSearchTerm = val;
        this.productSearch = new ProductSearch.List({merchantId:merchantId,term:val});
      }
    },
    select: function(orders){
      this.attr('selectedOrder', orders);
    },
    save: function(order) {
      order.save();
      this.removeAttr('selectedOrder');
    }
  }
});

var template = can.view("appMustache");
$("body").html(template);
