<?php 
/** 
  * Copyright: dtbaker 2012
  * Licence: This licence entitles you to use this application on a single installation only. 
  * More licence clarification available here:  http://codecanyon.net/wiki/support/legal-terms/licensing-terms/ 
  * Deploy: 872 861506aca0575d691626a677b73958cd
  * Envato: 646ea150-0482-4175-ae26-14effba4a0ed
  * Package Date: 2012-03-14 14:19:54 
  * IP Address: 127.0.0.1
  */

if(!$job_safe)die('denied');

$job_task_creation_permissions = module_job::get_job_task_creation_permissions();

$job_id = (int)$_REQUEST['job_id'];
$job = module_job::get_job($job_id);
$staff_members = module_user::get_staff_members();
$staff_member_rel = array();
foreach($staff_members as $staff_member){
    $staff_member_rel[$staff_member['user_id']] = $staff_member['name'];
}

if($job_id>0 && $job['job_id']==$job_id){
    $module->page_title = _l('Job: %s',$job['name']);
}else{
    $module->page_title = _l('Job: %s',_l('New'));
}

// check permissions.
if(class_exists('module_security',false)){
    if($job_id>0 && $job['job_id']==$job_id){
        module_security::check_page(array(
            'category' => 'Job',
            'page_name' => 'Jobs',
            'module' => 'job',
            'feature' => 'edit',
		));
    }else{
		module_security::check_page(array(
			'category' => 'Job',
            'page_name' => 'Jobs',
            'module' => 'job',
            'feature' => 'create',
		));
	}
	module_security::sanatise_data('job',$job);
}

$job_tasks = module_job::get_tasks($job_id);

if(class_exists('module_import_export',false)){
    if(module_job::can_i('view','Export Job Tasks')){
        module_import_export::enable_pagination_hook(
            // what fields do we pass to the import_export module from this job tasks?
            array(
                'name' => 'Job Tasks Export',
                'fields'=>array(
                    'Job Name' => 'job_name',
                    'Task ID' => 'task_id',
                    'Order' => 'task_order',
                    'Short Description' => 'description',
                    'Long Description' => 'long_description',
                    'Hours' => 'hours',
                    'Hours Completed' => 'completed',
                    'Amount' => 'amount',
                    'Billable' => 'billable',
                    'Fully Completed' => 'fully_completed',
                    'Date Due' => 'date_due',
                    'Invoice #' => 'invoice_number',
                    'Staff Member' => 'user_name',
                    'Approval Required' => 'approval_required',
                ),
            )
        );
        if(isset($_REQUEST['import_export_go']) && $_REQUEST['import_export_go'] == 'yes'){
            // do the task export.
            module_import_export::run_pagination_hook($job_tasks);
        }
    }
    if(module_job::can_i('view','Import Job Tasks')){
        $import_tasks_link = module_import_export::import_link(
            array(
                'callback'=>'module_job::handle_import_tasks',
                'name'=>'Job Tasks',
                'job_id'=>$job_id,
                'return_url'=>$_SERVER['REQUEST_URI'],
                'fields'=>array(
                    //'Job Name' => 'job_name',
                    'Task ID' => array('task_id',false,'The existing system ID for this task. Will overwrite existing task ID. Leave blank to create new task.'),
                    'Order' => array('task_order',false,'The numerical order the tasks will appear in.'),
                    'Short Description' => array('description',true),
                    'Long Description' => 'long_description',
                    'Hours' => 'hours',
                    'Hours Completed' => 'completed',
                    'Amount' => 'amount',
                    'Billable' => array('billable',false,'1 for billable, 0 for non-billable'),
                    'Fully Completed' => array('fully_completed',false,'1 for fully completed, 0 for not completed'),
                    'Date Due' => array('date_due',false,'When this task is due for completion'),
                    //'Invoice #' => 'invoice_number',
                    'Staff Member' => array('user_name',false,'One of: '.implode(', ',$staff_member_rel)),
                    'Approval Required' => array('approval_required',false,'1 if the administrator needs to approve this task, 0 if it does not require approval'),
                ),
            )
        );
    }
}

?>

<script type="text/javascript">
    var completed_tasks_hidden = false; // set with session variable / cookie
    var editing_task_id = false;
    var loading_task_html = '<tr class="task_edit_loading"><td colspan="8" align="center"><?php _e('Loading...');?></td></tr>';
    function show_completed_tasks(){
        $('.tasks_completed').show(); $('#show_completed_tasks').hide(); $('#hide_completed_tasks').show();
        set_task_numbers();
        Set_Cookie('job_tasks_hide','no');
        return true;
    }
    function hide_completed_tasks(){
        $('.tasks_completed').hide(); $('#show_completed_tasks').show(); $('#hide_completed_tasks').hide();
        set_task_numbers();
        Set_Cookie('job_tasks_hide','yes');
        return true;
    }
    function setamount(a,task_id){
        var ee = parseFloat(a);
        if(ee>0){
            $('#'+task_id+'taskamount').val(ee * <?php echo $job['hourly_rate'];?>);
            $('#'+task_id+'complete_hour').val(ee);
        }
    }
    function canceledittask(){
        if(editing_task_id){
            //$('#task_edit_'+editing_task_id).html(loading_task_html);
            // we have to load the task preview back in for this task.
            refresh_task_preview(editing_task_id);
            editing_task_id = false;
        }
        //$('.task_edit').hide();
        //$('.task_preview').show();

    }
    function clear_create_form(){
        $('#new_task_long_description').val('');
        $('#new_task_description').val('');
        $('#new_task_description')[0].focus();
    }
    function refresh_task_preview(task_id,html){

        var loading_placeholder = $(loading_task_html);
        loading_placeholder.addClass($('.task_row_'+task_id+':first').hasClass('odd') ? 'odd' : 'even');
        var h = 0;
        $('.task_row_'+task_id+'').each(function(){h+=$(this).height();});
        loading_placeholder.height(h);
        loading_placeholder.addClass('task_row_'+task_id);
        var existing_rows = $('.task_row_'+task_id+'');
        $('.task_row_'+task_id+':last').after(loading_placeholder);
        existing_rows.remove();
        if(html){
            // already provided by iframe callback
            existing_rows = $('.task_row_'+task_id+'');
            $('.task_row_'+task_id+':last').after(html);
            existing_rows.remove();
            set_task_numbers();

            <?php if(module_config::c('job_tasks_allow_sort',1)){ ?>
            $('#job_task_listing').sortable('enable');
            <?php } ?>
        }else{
            // do ajax cal to grab the updated task html
            $.ajax({
                url: '<?php echo module_job::link_ajax_task($job_id,false); ?>',
                data: {task_id:task_id,get_preview:1},
                method: 'POST',
                dataType: 'json',
                success: function(r){
                    var existing_rows = $('.task_row_'+r.task_id+'');
                    // case for adding this at the very first row.
                    if(r.after_task_id == 0){
                        $('.job_task_wrapper').prepend(r.html);
                    }else{
                        $('.task_row_'+r.after_task_id+':last').after(r.html);
                    }
                    existing_rows.remove();
                    set_task_numbers();
                    <?php if(module_config::c('job_tasks_allow_sort',1)){ ?>
                    $('#job_task_listing').sortable('enable');
                    <?php } ?>
                    // update the job summary
                    $('#job_summary').html(r.summary_html);
                }
            });
        }

    }
    function edittask(task_id,hours){
        canceledittask();
        editing_task_id = task_id;

        <?php if(module_config::c('job_tasks_allow_sort',1)){ ?>
        $('#job_task_listing').sortable('disable');
        <?php } ?>

        // load in the edit task bit via ajax.
        var loading_placeholder = $(loading_task_html);
        loading_placeholder.addClass($('.task_row_'+task_id+':first').hasClass('odd') ? 'odd' : 'even');
        loading_placeholder.height($('.task_row_'+task_id+':first').height());
        loading_placeholder.addClass('task_row_'+task_id);
        var existing_rows = $('.task_row_'+task_id+'');
        $('.task_row_'+task_id+':last').after(loading_placeholder);
        existing_rows.remove();
        $.ajax({
            url: '<?php echo module_job::link_ajax_task($job_id,false); ?>',
            data: {task_id:editing_task_id, hours:hours},
            method: 'POST',
            dataType: 'json',
            success: function(r){
                var existing_rows = $('.task_row_'+task_id+'');
                $('.task_row_'+r.task_id+':last').after($(r.html).addClass($('.task_row_'+task_id+':first').hasClass('odd') ? 'odd' : 'even')); // this inserts two rows!
                existing_rows.remove();
                load_calendars();
                if(r.hours>0){
                    <?php if(module_config::c('job_task_log_all_hours',1)){
                        // dont want to set hours. just tick the box.
                    }else{ ?>
                    $('#complete_'+r.task_id).val(r.hours);
                    <?php } ?>
                    if(typeof $('#complete_t_'+r.task_id)[0] != 'undefined'){
                        //$('#complete_t_'+r.task_id)[0].checked = true;
                        $('#complete_t_label_'+r.task_id).css('font-weight','bold');
                    }else{
                        $('#complete_'+r.task_id)[0].select();
                    }
                }else{ // if(r.hours == 0){
                    $('#task_desc_'+r.task_id)[0].focus();
                    //$('#task_desc_'+r.task_id)[0].select();
                }/*else{
                    if(typeof $('#complete_'+r.task_id)[0] != 'undefined'){
                        $('#complete_'+r.task_id)[0].focus();
                    }
                }*/
            }
        });

        return false;
    }
    function delete_task_hours(task_id,task_log_id){
        if(confirm('<?php _e('Really delete task hours?');?>') && task_id && task_log_id){
            $.ajax({
                url: '<?php echo module_job::link_ajax_task($job_id,false); ?>',
                data: {task_id:task_id, delete_task_log_id:task_log_id},
                method: 'POST',
                dataType: 'text',
                success: function(r){
                    refresh_task_preview(task_id,false);
                }
            });
        }
    }
    function set_task_numbers(){
        // iterate through the tasks in the list
        // set the values from 1 counting up in each cell
        // if one of the values doesn't match what we are dispalying
        // we update the number via ajax in the system.
        // we also set the odd/even classes in the tables so that it looks pretty after updating.
        var task_number = 1;
        var odd_even = 1;
        var do_update = false;
        var update_task_orders = {
            update_task_order: 1
        };
        $('tr.task_preview').each(function(){
            $(this).removeClass('odd');
            $(this).removeClass('even');
            if($(this).is(':visible')){
                $(this).addClass(odd_even++ % 2 ? 'odd' : 'even');
            }
            var current_order = parseInt($('.task_order',this).html());
            <?php if($job['auto_task_numbers'] == 0){ ?>
                // automatic task numbers.
                if(current_order != task_number){
                    do_update=true;
                    update_task_orders['task_order['+$(this).attr('rel')+']'] = task_number;
                }
                $('.task_order',this).html(task_number);
                task_number++;
            <?php }else if($job['auto_task_numbers'] == 1){ ?>
                // manual task numbers.
                task_number = Math.max(current_order,task_number) + 1;
            <?php } ?>
        });
        if(do_update){
            $.ajax({
                url: '<?php echo module_job::link_ajax_task($job_id,false); ?>',
                method: 'POST',
                data: update_task_orders
            });
        }
        // todo - later on we call this as we dynamically re-arrange the cells in this table..

        // then we set the next available task number in the create new task number area.
        $('#next_task_number').val(task_number);


        <?php if(module_config::c('job_tasks_allow_sort',1)){ ?>
        $('#job_task_listing').sortable('refresh');
        <?php } ?>
    }
    $(function(){
        /*$('.task_editable').click(function(event){
            event.preventDefault();
            edittask($(this).attr('rel'),-1);
            return false;
        });*/
        set_task_numbers();
        <?php if(module_config::c('job_tasks_allow_sort',1)){ ?>
        $( "#job_task_listing" ).sortable({
			items: ".task_preview",
            axis: 'y',
            stop: function(){
                set_task_numbers();
            },
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) {
                    // Set helper cell sizes to match the original sizes
                    $(this).width($originals.eq(index).width())
                });
                return $helper;
            }
		});
        <?php } ?>
        $('.task_toggle_long_description').live('click',function(event){
            event.preventDefault();
            $(this).parent().find('.task_long_description').slideToggle(function(){
                if($('textarea.edit_task_long_description').length>0){
                    $('textarea.edit_task_long_description')[0].focus();
                }
            });
            return false;
        });
        if(Get_Cookie('job_tasks_hide')=='yes'){
            hide_completed_tasks();
        }

        $('#save_saved').click(function(){
            // set a flag and submit our form.
            if($('#default_task_list_id').val() == ''){
                alert('<?php _e('Please enter a name for this saved task listing');?>');
                return false;
            }
            if(confirm('<?php _e('Really save these tasks as a default task listing?');?>')){
                $('#default_tasks_action').val('save_default');
                $('#job_form')[0].submit();
            }
        });
        $('#insert_saved').click(function(){
            // set a flag and submit our form.
            $('#default_tasks_action').val('insert_default');
            $('#job_form')[0].submit();
        });
    });
</script>

	

	<table cellpadding="10" width="100%">
		<tbody>
			<tr>
				<td valign="top" width="35%">


                <form action="" method="post" id="job_form">
                    <input type="hidden" name="_process" value="save_job" />
                    <input type="hidden" name="job_id" value="<?php echo $job_id; ?>" />
                    <input type="hidden" name="customer_id" value="<?php echo $job['customer_id']; ?>" />


                    <?php

                    $fields = array(
                    'fields' => array(
                        'name' => 'Name',
                    ));
                    module_form::set_required(
                        $fields
                    );
                    module_form::prevent_exit(array(
                        'valid_exits' => array(
                            // selectors for the valid ways to exit this form.
                            '.submit_button',
                            '.save_task',
                            '.delete',
                            '.task_defaults',
                        ))
                    );


                    ?>

					<h3><?php echo _l('Job Details'); ?></h3>



					<table border="0" cellspacing="0" cellpadding="2" class="tableclass tableclass_form tableclass_full">
						<tbody>
							<tr>
								<th class="width1">
									<?php echo _l('Job Title'); ?>
								</th>
								<td>
									<input type="text" name="name" id="name" value="<?php echo htmlspecialchars($job['name']); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Type'); ?>
								</th>
								<td>
									<?php echo print_select_box(module_job::get_types(),'type',$job['type'],'',true,false,true); ?>
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Hourly Rate'); ?>
								</th>
								<td>
                                    <?php echo currency('<input type="text" name="hourly_rate" class="currency" value="'.$job['hourly_rate'].'">');?>
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Status'); ?>
								</th>
								<td>
									<?php echo print_select_box(module_job::get_statuses(),'status',$job['status'],'',true,false,true); ?>
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Start Date'); ?>
								</th>
								<td>
									<input type="text" name="date_start" class="date_field" value="<?php echo print_date($job['date_start']);?>">
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Due Date'); ?>
								</th>
								<td>
									<input type="text" name="date_due" class="date_field" value="<?php echo print_date($job['date_due']);?>">
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Finished Date'); ?>
								</th>
								<td>
									<input type="text" name="date_completed" class="date_field" value="<?php echo print_date($job['date_completed']);?>">
								</td>
							</tr>
                            <?php if(module_config::c('job_allow_staff_assignment',1)){ ?>
                            <tr>
                                <th>
                                    <?php _e('Staff Member');?>
                                </th>
                                <td>
                                    <?php
                                    echo print_select_box($staff_member_rel,'user_id',$job['user_id']);
                                    _h('Assign a staff member to this job. You can also assign individual tasks to different staff members.');
                                    ?>
                                </td>
                            </tr>
                            <?php } ?>
							<tr>
								<th>
									<?php echo _l('Tax'); ?>
								</th>
								<td>
									<input type="text" name="total_tax_name" value="<?php echo htmlspecialchars($job['total_tax_name']);?>" style="width:30px;">
									@
                                    <input type="text" name="total_tax_rate" value="<?php echo htmlspecialchars($job['total_tax_rate']);?>" style="width:35px;">%

								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Currency'); ?>
								</th>
								<td>
									<?php echo print_select_box(get_multiple('currency','','currency_id'),'currency_id',$job['currency_id'],'',false,'code'); ?>
								</td>
							</tr>
                            <?php if((int)$job_id>0 && class_exists('module_file',false) && module_file::can_i('view','Files')){ ?>
							<tr>
								<th>
									<?php echo _l('Files'); ?>
								</th>
								<td>
									<?php $files = module_file::get_files(array('job_id'=>$job_id));
                                    if(count($files) > 0){
                                        ?>
                                        <a href="<?php echo module_file::link_generate(false,array('arguments'=>array(
                                                                                            'job_id' => $job_id,
                                                                                        )));?>"><?php echo _l('View all %d files in this job',count($files));?></a>
                                        <?php
                                    }else{
                                echo _l("This job has %d files",count($files));
                                    }
                                echo '<br/>';
                                ?>
                                    <a href="<?php echo module_file::link_generate('new',array('arguments'=>array(
                                                                                            'job_id' => $job_id,
                                                                                        ))); ?>"><?php _e('Add New File');?></a>
								</td>
							</tr>
                            <?php } ?>
						</tbody>
                        <?php
                         module_extra::display_extras(array(
                            'owner_table' => 'job',
                            'owner_key' => 'job_id',
                            'owner_id' => $job['job_id'],
                            'layout' => 'table_row',
                            )
                        );
                        ?>
					</table>

                    <?php if((int)$job_id>0){ ?>
                    <h3><?php echo _l('External Customer Link'); ?></h3>
                    <table border="0" cellspacing="0" cellpadding="2" class="tableclass tableclass_form tableclass_full">
						<tbody>
							<tr>
                                <td>
                                    <a href="<?php echo module_job::link_public($job_id);?>" target="_blank"><?php echo _l('Click to view external link');?></a>
                                    <?php _h('You can send this link to the customer and they can view progress on their job. They can also view a list of any invoices attached to this job. This is VERY useful to stop customers asking you "how far along are you" with a job because they can see exactly where you have logged up to in the system.');?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php } ?>

                    <?php
                    if($job_id && $job_id!='new'){
                        $note_summary_owners = array();
                        // generate a list of all possible notes we can display for this job.
                        // display all the notes which are owned by all the sites we have access to

                        module_note::display_notes(array(
                            'title' => 'Job Notes',
                            'owner_table' => 'job',
                            'owner_id' => $job_id,
                            'view_link' => module_job::link_open($job_id),
                            )
                        );
                    }
                    ?>
                    

                    <h3><?php echo _l('Advanced'); ?></h3>
                    <table border="0" cellspacing="0" cellpadding="2" class="tableclass tableclass_form tableclass_full">
						<tbody>

							<tr>
								<th class="width1">
									<?php echo _l('Assign '.module_config::c('project_name_single','Website')); ?>
								</th>
								<td>
                                    <?php
                                    $c = array();
                                    // change between websites within this customer?
                                    // or websites all together?
                                    $res = module_website::get_websites(array('customer_id'=>(isset($_REQUEST['customer_id'])?(int)$_REQUEST['customer_id']:false)));
                                    //$res = module_website::get_websites();
                                    while($row = array_shift($res)){
                                        $c[$row['website_id']] = $row['name'];
                                    }
                                    echo print_select_box($c,'website_id',$job['website_id']);
                                    ?>
                                    <?php if($job['website_id'] && module_website::can_i('view','Websites')){ ?>
                                        <a href="<?php echo module_website::link_open($job['website_id'],false);?>"><?php _e('Open');?></a>
                                    <?php } ?>
                                    <?php _h('This will be the '.module_config::c('project_name_single','Website').' this job is assigned to - and therefor the customer. Every job should have a'.module_config::c('project_name_single','Website').' assigned. Clicking the open link will take you to the '.module_config::c('project_name_single','Website'));?>
								</td>
							</tr>
							<tr>
								<th>
									<?php echo _l('Assign Customer'); ?>
								</th>
								<td>
                                    <?php
                                    $c = array();
                                    $customers = module_customer::get_customers();
                                    foreach($customers as $customer){
                                        $c[$customer['customer_id']] = $customer['customer_name'];
                                    }
                                    echo print_select_box($c,'customer_id',$job['customer_id']);
                                    ?>
                                    <?php if($job['customer_id'] && module_customer::can_i('view','Customers')){ ?>
                                        <a href="<?php echo module_customer::link_open($job['customer_id'],false);?>"><?php _e('Open');?></a>
                                    <?php } ?>
								</td>
							</tr>
                            <?php
                            if((int)$job_id > 0){
                                // see if this job was renewed from anywhere
                                $job_history = module_job::get_jobs(array('renew_job_id'=>$job_id));
                                if(count($job_history)){
                                    foreach($job_history as $job_h){
                                    ?>

                                    <tr>
                                        <th class="width1">
                                            <?php echo _l('Renewal History'); ?>
                                        </th>
                                        <td>
                                            <?php echo _l('This job was renewed from %s job on %s',module_job::link_open($job_h['job_id'],true),print_date($job_h['date_renew'])); ?>
                                        </td>
                                    </tr>
                                    <?php
                                    }
                                }
                            }
                            ?>
							<tr>
								<th class="width1">
									<?php echo _l('Renewal Date'); ?>
								</th>
								<td>
                                    <?php if($job['renew_job_id']){
                                        echo _l('This job was renewed on %s.',print_date($job['date_renew']));
                                        echo '<br/>';
                                        echo _l('A new job was created, please click <a href="%s">here</a> to view it.',module_job::link_open($job['renew_job_id']));
                                    }else{
                                        ?>
                                        <input type="text" name="date_renew" class="date_field" value="<?php echo print_date($job['date_renew']);?>">
                                        <?php 
                                        if($job['date_renew'] && $job['date_renew'] != '0000-00-00' && strtotime($job['date_renew']) <= strtotime('+'.module_config::c('alert_days_in_future',5).' days')){
                                            // we are allowed to generate this renewal.
                                            ?>
                                            <input type="button" name="generate_renewal_btn" value="<?php echo _l('Generate Renewal');?>" class="submit_button" onclick="$('#generate_renewal_gogo').val(1); this.form.submit();">
                                            <input type="hidden" name="generate_renewal" id="generate_renewal_gogo" value="0">

                                            <?php
                                            _h('A renewal is available for this job. Clicking this button will create a new job based on this job, and set the renewal reminder up again for the next date.');
                                        }else{
                                            _h('You will be reminded to renew this job on this date. You will be given the option to renew this job closer to the renewal date (a new button will appear).');
                                        }
                                } ?>
								</td>
							</tr>
                            <?php if((int)$job_id>0){ ?>
                            <tr>
                                <th>
                                    <?php _e('Task CSV Data');?>
                                </th>
                                <td>
                                    <?php
                                    // hack to add a "export" link to this page
                                    if(class_exists('module_import_export',false)){
                                        if(module_job::can_i('view','Export Job Tasks')){ ?>
                                            <a href="<?php echo module_job::link_open($job_id,false).'&import_export_go=yes';?>" class=""><?php _e('Export Tasks');?></a>
                                        <?php }
                                        if(module_job::can_i('view','Import Job Tasks') && module_job::can_i('view','Export Job Tasks')){
                                            echo ' / ';
                                        }
                                        if(module_job::can_i('view','Import Job Tasks')){ ?>
                                            <a href="<?php echo $import_tasks_link;?>" class=""><?php _e('Import Tasks');?></a>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if(module_config::c('job_enable_default_tasks',1)){ ?>
                            <tr>
                                <th>
                                    <?php _e('Task Defaults'); ?>
                                </th>
                                <td>
                                    <?php
                                    $job_default_tasks = module_job::get_default_tasks();
                                    echo print_select_box($job_default_tasks,'default_task_list_id','','',true,'',true);
                                    ?>
                                    <?php if((int)$job_id>0){ ?>
                                    <input type="button" name="s" id="save_saved" value="<?php _e('Save');?>" class="task_defaults">
                                    <?php } ?>
                                    <input type="button" name="i" id="insert_saved" value="<?php _e('Insert');?>" class="task_defaults">
                                    <input type="hidden" name="default_tasks_action" id="default_tasks_action" value="0">
                                    <?php _h('Here you can save the current tasks as defaults to be used later, or insert a previously saved set of defaults.'); ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if(module_config::c('job_show_task_numbers',1)){ ?>
                        <tr>
                            <th>
                                <?php _e('Task numbers');?>
                            </th>
                            <td>
                                <?php echo print_select_box(array(
                                                           0 => _l('Automatic'),
                                                           1 => _l('Manual'),
                                                           2 => _l('Hidden'),
                                                       ),'auto_task_numbers',$job['auto_task_numbers'],'',false);?>

                            </td>
                        </tr>
    <?php } ?>
						</tbody>
					</table>

                    <p align="center">
                        <input type="submit" name="butt_save" id="butt_save" value="<?php echo _l('Save job'); ?>" class="submit_button save_button" />
                        <?php if((int)$job_id){ ?>
                        <input type="submit" name="butt_del" id="butt_del" value="<?php echo _l('Delete'); ?>" class="submit_button delete_button" />
                        <?php } ?>
                        <input type="button" name="cancel" value="<?php echo _l('Cancel'); ?>" onclick="window.location.href='<?php echo module_job::link_open(false); ?>';" class="submit_button" />
                    </p>

                    </form>

				</td>
                <td valign="top">


                    <?php if(module_config::c('job_ajax_tasks',1)){ ?>
                        <iframe name="job_task_ajax_submit" id="job_task_ajax_submit" src="about:blank" style="display:none; width:0; height:0;" frameborder="0"></iframe>
                    <?php } ?>

                    <form action="" method="post" id="job_task_form" <?php if(module_config::c('job_ajax_tasks',1)){ ?> target="job_task_ajax_submit"<?php } ?>>
                        <input type="hidden" name="_process" value="save_job<?php if(module_config::c('job_ajax_tasks',1)) echo '_tasks_ajax'; ?>" />
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>" />
                        <input type="hidden" name="customer_id" value="<?php echo $job['customer_id']; ?>" />

                        <?php

                        module_form::set_default_field('new_task_description');
                        module_form::prevent_exit(array(
                            'valid_exits' => array(
                                // selectors for the valid ways to exit this form.
                                '.submit_button',
                                '.save_task',
                                '.delete',
                            ))
                        );


                        ?>


                <?php if(module_job::can_i('view','Job Tasks')){ ?>

					<h3>
                        <span class="button" id="show_completed_tasks" style="display:none;">
                            <a href="javascript:show_completed_tasks();" class="uibutton"><?php _e('Show Completed Tasks');?></a>
                        </span>
                        <span class="button" id="hide_completed_tasks">
                            <a href="javascript:hide_completed_tasks();" class="uibutton"><?php _e('Hide Completed Tasks');?></a>
                        </span>
                        <?php echo _l('Job Tasks %s',($job['total_percent_complete']>0 ? _l('(%s%% completed)',$job['total_percent_complete']*100) : '')); ?>

                    </h3>

                            <?php
                            $show_task_numbers = (module_config::c('job_show_task_numbers',1) && $job['auto_task_numbers'] != 2);
                            ?>

                    <table border="0" cellspacing="0" cellpadding="2" class="tableclass tableclass_rows tableclass_full" id="job_task_listing">
                        <thead>
                        <tr>
                            <?php if($show_task_numbers){ ?>
                            <th width="10">#</th>
                            <?php } ?>
                            <th class="task_column task_width"><?php _e('Description');?></th>
                            <th width="10"><?php _e('Hours');?></th>
                            <th width="79"><?php _e('Amount');?></th>
                            <th width="83"><?php _e('Due Date');?></th>
                            <?php if(module_config::c('job_allow_staff_assignment',1)){ ?>
                            <th width="78"><?php _e('Staff');?></th>
                            <?php } ?>
                            <th width="32" nowrap="nowrap">%</th>
                            <th width="60"> </th>
                        </tr>
                        </thead>
                        <?php
                            if(module_security::is_page_editable() && module_job::can_i('create','Job Tasks') && $job_task_creation_permissions != _JOB_TASK_CREATION_NOT_ALLOWED){ ?>
						<tbody>
                        <tr>
                            <?php if($show_task_numbers){ ?>
                                <td valign="top" style="padding:0.3em 0;">
                                    <input type="text" name="job_task[new][task_order]" value="" id="next_task_number" size="3" class="edit_task_order">
                                </td>
                            <?php } ?>
                            <td valign="top">
                                <input type="text" name="job_task[new][description]" id="new_task_description" class="edit_task_description" value="">
                                <a href="#" class="task_toggle_long_description">&raquo;</a>
                                <div class="task_long_description">
                                    <textarea name="job_task[new][long_description]" id="new_task_long_description" class="edit_task_long_description"></textarea>
                                </div>
                            </td>
                            <td valign="top">
                                <input type="text" name="job_task[new][hours]" value="" size="3" style="width:25px;" onchange="setamount(this.value,'new');" onkeyup="setamount(this.value,'new');">
                            </td>
                            <td valign="top" nowrap="">
                                <?php echo currency('<input type="text" name="job_task[new][amount]" value="" id="newtaskamount" class="currency">');?>
                            </td>
                            <td valign="top">
                                <input type="text" name="job_task[new][date_due]" value="<?php echo print_date($job['date_due']);?>" class="date_field">
                            </td>
                            <?php if(module_config::c('job_allow_staff_assignment',1)){ ?>
                                <td valign="top">
                                    <?php echo print_select_box($staff_member_rel,'job_task[new][user_id]',
                                        isset($staff_member_rel[module_security::get_loggedin_id()]) ? module_security::get_loggedin_id() : false, 'job_task_staff_list', ''); ?>
                                </td>
                            <?php } ?>
                            <td valign="top">
                                <input type="checkbox" name="job_task[new][new_fully_completed]" value="1">
                            </td>
                            <td align="center" valign="top">
                                <input type="submit" name="save" value="<?php _e('New Task');?>" class="save_task">
                            </td>
                        </tr>
						</tbody>
                        <?php } ?>
                        <tbody class="job_task_wrapper">
                        <?php
                        $c=0;
                        $task_number = 0;
                        foreach($job_tasks as $task_id => $task_data){
                            $task_number++;
                            if(module_security::is_page_editable() && module_job::can_i('edit','Job Tasks')){ /*?>
                                <tbody id="task_edit_<?php echo $task_id;?>" style="display:none;" class="task_edit"></tbody>
                            <?php*/  } else {
                                $task_editable = false;
                            }
                            echo module_job::generate_task_preview($job_id,$job,$task_id,$task_data);
                        } ?>
                        </tbody>
                        </table>
                        <?php if((int)$job_id>0){
                            ?> <div id="job_summary"> <?php echo module_job::generate_job_summary($job_id,$job); ?> </div> <?php
                        } ?>


            <?php }  // end can i view job tasks ?>

                <?php if(module_invoice::can_i('view','Invoices') && (int)$job_id > 0){ ?>
                <h3><?php _e('Job Invoices:');?></h3>
                    <?php
                    $job_invoices = module_invoice::get_invoices(array('job_id'=>$job_id));
                    if(!count($job_invoices)){ ?>

                            <p>
                                <?php _e('There are no invoices for this job yet. Please create and complete a task above in order to create an invoice.'); ?>
                            </p>

                    <?php }else{ ?>

                <table class="tableclass tableclass_rows tableclass_full">
                   <thead>
                    <tr class="title">
                        <th><?php echo _l('Invoice Number'); ?></th>
                        <th><?php echo _l('Status'); ?></th>
                        <th><?php echo _l('Due Date'); ?></th>
                        <th><?php echo _l('Sent Date'); ?></th>
                        <th><?php echo _l('Paid Date'); ?></th>
                        <th><?php echo _l('Invoice Total'); ?></th>
                        <th><?php echo _l('Amount Due'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php
                        $c=0;
                        foreach($job_invoices as $invoice){
                            $invoice = module_invoice::get_invoice($invoice['invoice_id']);
                            ?>
                            <tr class="<?php echo ($c++%2)?"odd":"even"; ?>">
                                <td class="row_action">
                                    <?php echo module_invoice::link_open($invoice['invoice_id'],true);?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($invoice['status']); ?>
                                </td>
                                <td>
                                    <?php
                                    if((!$invoice['date_paid']||$invoice['date_paid']=='0000-00-00') && strtotime($invoice['date_due']) < time()){
                                        echo '<span class="error_text">';
                                        echo print_date($invoice['date_due']);
                                        echo '</span>';
                                    }else{
                                        echo print_date($invoice['date_due']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo print_date($invoice['date_sent']);?>
                                </td>
                                <td>
                                    <?php echo print_date($invoice['date_paid']);?>
                                </td>
                                <td>
                                    <?php echo dollar($invoice['total_amount'],true,$invoice['currency_id']);?>
                                </td>
                                <td>
                                    <?php echo dollar($invoice['total_amount_due'],true,$invoice['currency_id']);?>
                                    <?php if($invoice['total_amount_credit'] > 0){
                                ?>
                                <span class="success_text"><?php echo _l('Credit: %s',dollar($invoice['total_amount_credit'],true,$invoice['currency_id']));?></span>
                                <?php
                            } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
                <?php } ?>


</form>

                </td>
			</tr>
		</tbody>
	</table>

