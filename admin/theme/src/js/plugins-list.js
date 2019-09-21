import DataTable from 'datatables.net';
import 'datatables.net-bs4';
import 'datatables.net-dt';
import 'datatables.net-select';
import 'datatables.net-buttons';

$(function() {
  const plugins_list = $('#plugins-list');
  let datatable;

  if (plugins_list.length > 0) {
    datatable = plugins_list.DataTable({
      language: plugins_list_config.language
    });
  }
});
