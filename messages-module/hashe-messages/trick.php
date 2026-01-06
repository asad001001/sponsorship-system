<?php
add_action('pre_get_posts', 'filter_posts_list');
function filter_posts_list($query)
{
    //$pagenow holds the name of the current page being viewed
    global $pagenow;
    $post_type = $query->get('post_type');

    //Check the post type and whether its the list page, then set the taxonomy
    if($post_type == 'my_cpt' && 'edit.php' == $pagenow)
    {
        //global $query's set() method for setting the taxonomy
        $query->set('my_taxonomy', 'my_taxonomy_value');
    }
}
?>
    <table class="widefat fixed" cellspacing="0">
        <thead>
        <tr>

                <th id="cb" class="manage-column column-cb check-column" scope="col">test</th> 
                <th id="columnname" class="manage-column column-columnname" scope="col">test1</th>
                <th id="columnname" class="manage-column column-columnname num" scope="col">test2</th> 

        </tr>
        </thead>

        <tfoot>
        <tr>

                <th class="manage-column column-cb check-column" scope="col">testf1</th>
                <th class="manage-column column-columnname" scope="col">testf2</th>
                <th class="manage-column column-columnname num" scope="col">testf3</th>

        </tr>
        </tfoot>

        <tbody>
            <tr class="alternate">
                <th class="check-column" scope="row"></th>
                <td class="column-columnname"></td>
                <td class="column-columnname"></td>
            </tr>
            <tr>
                <th class="check-column" scope="row"></th>
                <td class="column-columnname"></td>
                <td class="column-columnname"></td>
            </tr>
            <tr class="alternate" valign="top"> // this row contains actions
                <th class="check-column" scope="row"></th>
                <td class="column-columnname">
                    <div class="row-actions">
                        <span><a href="#">Action</a> |</span>
                        <span><a href="#">Action</a></span>
                    </div>
                </td>
                <td class="column-columnname"></td>
            </tr>
            <tr valign="top"> // this row contains actions
                <th class="check-column" scope="row"></th>
                <td class="column-columnname">
                    <div class="row-actions">
                        <span><a href="#">Action</a> |</span>
                        <span><a href="#">Action</a></span>
                    </div>
                </td>
                <td class="column-columnname"></td>
            </tr>
        </tbody>
    </table>